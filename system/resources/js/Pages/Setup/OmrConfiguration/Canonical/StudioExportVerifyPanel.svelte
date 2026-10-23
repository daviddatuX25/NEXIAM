<script>
    import { onDestroy, onMount } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import { Link } from '@inertiajs/svelte';
    import {
        Check,
        ChevronDown,
        ChevronLeft,
        ChevronUp,
        Loader2,
        Printer,
        RotateCcw,
        Sparkles,
        X,
        AlertCircle,
        CheckCircle2,
    } from 'lucide-svelte';
    import { toastSuccess, toastError } from '@/lib/toast';
    import StudioShadingGuideModal from './StudioShadingGuideModal.svelte';
    import OmrPhysicalTestMethodCard from './OmrPhysicalTestMethodCard.svelte';
    import OmrDiagnosticEvidenceModal from '@/Components/Omr/Diagnostics/OmrDiagnosticEvidenceModal.svelte';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils';
    import {
        captureSourceForFile,
        challengeRecovery,
        createVerificationPoller,
        deriveCheckpointStates,
        deriveCurrentCheckpoint,
        DIGITAL_PHASES,
        hasResumablePhysicalChallenge,
        isTerminalRunStatus,
        phaseText,
        physicalChallengeResumeStage,
        registrationProfileReady,
    } from '@/lib/omrVerificationRun';
    let {
        draft = $bindable({}),
        urls = {},
        getCsrfToken = () => '',
        lifecycleBusy = false,
        kit_context = null,
        onBack = null,
        onBackToKit = null,
        verification_run = null,
        physical_run_id = null,
        marker_profile_status = null,
        onDraftLockVersion = () => {},
    } = $props();

    const STATUS_LABELS = {
        done: 'Done',
        active: 'In progress',
        blocked: 'Blocked',
        pending: 'Ready',
    };
    const isProfileQualified = $derived(
        registrationProfileReady(draft, marker_profile_status),
    );

    const isUploadedSource = $derived(
        ['pdf', 'image', 'docx', 'uploaded_original'].includes(draft?.source_kind),
    );
    const baseRun = $derived(
        verification_run ?? draft?.verification_run ?? null,
    );
    let runOverride = $state(null);
    const run = $derived(runOverride ?? baseRun);
    const recovery = $derived(challengeRecovery(run));
    let poller = $state(null);
    let isHydratingOverride = $state(null);
    const isHydrating = $derived(
        isHydratingOverride ?? Boolean(baseRun?.status_url),
    );
    let lastHydratedUrl = $state(null);
    let lastBaseRunId = $state(null);
    let lastDraftId = $state(null);
    let requestGeneration = 0;
    let challengeMode = $state('quick');
    let capturePageNo = $state(1);
    let selectedCaptureId = $state(null);
    let notice = $state('');
    let failure = $state(null);
    let busyFlags = $state({
        digital: false,
        compile: false,
        challenge: false,
        print: false,
        capture: false,
        review: false,
        discard: false,
    });
    let guideModalOpen = $state(false);
    let guideMethod = $state(null);

    const states = $derived(
        deriveCheckpointStates({
            run,
            draft,
            urls,
            physicalRunId: physical_run_id,
            markerProfileStatus: marker_profile_status,
        }).filter((state) => state.key !== 'qualification'),
    );
    const isComplete = $derived(
        states.length > 0 && states.every((state) => state.status === 'done'),
    );
    let expandedStepKeyOverride = $state(null);
    const activeStep = $derived(deriveCurrentCheckpoint(states));
    const defaultExpandedKey = $derived(
        activeStep &&
            (activeStep.status === 'active' ||
                activeStep.status === 'pending' ||
                (states.every((s) => s.status === 'done') &&
                    activeStep.status === 'done'))
            ? activeStep.key
            : null,
    );
    const expandedStepKey = $derived.by(() => {
        if (run?.status === 'needs_review') return 'physical_challenge';
        if (expandedStepKeyOverride === 'none') return null;
        const candidate = expandedStepKeyOverride ?? defaultExpandedKey;
        if (!candidate) return null;
        const target = states.find((s) => s.key === candidate);
        if (!target || target.status === 'blocked') return null;
        return candidate;
    });
    const isDigitalQueued = $derived(
        Boolean(run?.mode === 'digital' && run?.status === 'queued'),
    );
    const isDigitalRunning = $derived(
        Boolean(
            run?.mode === 'digital' && DIGITAL_PHASES.includes(run?.status),
        ),
    );

    function checkpointStatusLabel(state) {
        if (
            state.key === 'digital' &&
            state.status === 'pending' &&
            isDigitalQueued
        ) {
            return 'Waiting';
        }
        return STATUS_LABELS[state.status] ?? state.status;
    }

    function mergeRunPayload(previous, payload, fallbackStatus = null) {
        const runChanged = Boolean(
            previous &&
            payload?.run_id != null &&
            previous.run_id != null &&
            Number(payload.run_id) !== Number(previous.run_id),
        );
        const replacingRun = previous == null || runChanged;
        const next = replacingRun
            ? { ...(payload ?? {}) }
            : { ...(previous ?? {}), ...(payload ?? {}) };
        if (payload?.run_id == null && previous?.run_id != null)
            next.run_id = previous.run_id;
        if (payload?.status == null && fallbackStatus)
            next.status = fallbackStatus;
        if (
            payload?.attempt == null &&
            previous?.attempt != null &&
            !replacingRun
        )
            next.attempt = previous.attempt;
        if (
            payload?.status_url == null &&
            previous?.status_url &&
            !replacingRun
        )
            next.status_url = previous.status_url;
        if (payload?.challenge == null && previous?.challenge && !replacingRun)
            next.challenge = previous.challenge;
        else if (
            payload?.challenge != null &&
            previous?.challenge != null &&
            !replacingRun
        ) {
            next.challenge = { ...previous.challenge, ...payload.challenge };
        }
        if (payload?.evidence == null && previous?.evidence && !replacingRun)
            next.evidence = previous.evidence;
        if (replacingRun) {
            // A new run starts with no historical challenge/evidence or authority
            // flags copied from the old run, even when the start response is small.
            if (payload?.challenge === undefined) next.challenge = null;
            if (payload?.evidence === undefined) next.evidence = [];
            if (payload?.challenge_recovery === undefined)
                next.challenge_recovery = null;
            next.target_current = payload?.target_current !== false;
            next.target_stale = payload?.target_stale === true;
            next.digital_verification_current =
                payload?.digital_verification_current === true;
        } else if (payload?.challenge_recovery !== undefined) {
            next.challenge_recovery = payload.challenge_recovery;
        } else if (previous?.challenge_recovery !== undefined) {
            next.challenge_recovery = previous.challenge_recovery;
        }
        return next;
    }

    function toggleStep(key) {
        const target = states.find((s) => s.key === key);
        if (!target || target.status === 'blocked') return;
        if (expandedStepKey === key) {
            expandedStepKeyOverride = 'none';
        } else {
            expandedStepKeyOverride = key;
        }
    }

    function toggleAll() {
        if (expandedStepKey !== null) {
            expandedStepKeyOverride = 'none';
        } else {
            const actionable =
                states.find((s) => s.status === 'active') ??
                states.find((s) => s.status === 'pending');
            expandedStepKeyOverride =
                actionable?.key ??
                states.find((s) => s.status !== 'blocked')?.key ??
                'none';
        }
    }
    function selectCaptureMethod(method) {
        if (!METHOD_KEYS.includes(method)) return;
        activeCaptureMethod = method;
        activeMethod = method;
        expandedStepKeyOverride = 'physical_challenge';
    }
    const digitalPassedCheck = $derived(states[0]?.status === 'done');

    const METHOD_KEYS = ['pre_shaded', 'hand_shaded'];
    let activeMethod = $state('pre_shaded');
    let activeCaptureMethod = $state('pre_shaded');
    let evidenceModalOpen = $state(false);
    let evidencePage = $state(null);
    let evidenceLoading = $state(false);
    let evidenceError = $state(null);
    let evidenceArea = $state('all');
    let evidenceFilter = $state(null);
    let evidencePageNo = $state(1);
    let evidencePerPage = $state(25);
    let evidenceTab = $state('aligned');
    let evidenceRequestGeneration = 0;
    const evidenceReviewReasons = [
        {
            code: 'faint_or_partial_mark',
            label: 'Faint shading missed by machine',
        },
        { code: 'multiple_marks', label: 'Multiple marks — one intended' },
        {
            code: 'incorrect_detection',
            label: 'Machine selected wrong option',
        },
        { code: 'intentional_blank', label: 'Confirmed intentionally blank' },
        { code: 'insufficient_evidence', label: 'Unable to determine' },
    ];

    function methodPageCount(methodData) {
        return Math.max(
            1,
            Number(methodData?.required_page_count) ||
                Number(methodData?.pages?.length) ||
                Number(methodData?.challenge?.sheet_pages?.length) ||
                Number(draft?.authoring?.canonical?.settings?.page_count) ||
                Number(draft?.authoring?.pages?.length) ||
                1,
        );
    }

    const methodCards = $derived.by(() =>
        METHOD_KEYS.map((method) => {
            const persisted = Array.isArray(run?.methods)
                ? run.methods.find((candidate) => candidate.method === method)
                : null;
            if (persisted) return persisted;

            // Older summary payloads carry one run-level challenge. Keep it visible
            // on the pre-shaded card until the status projection is refreshed with
            // method-owned rows; never infer a hand-shaded challenge from that shape.
            const legacyChallenge =
                method === 'pre_shaded' && run?.challenge
                    ? run.challenge
                    : null;
            const legacyState = legacyChallenge
                ? legacyChallenge.status === 'printed' ||
                  run?.status === 'awaiting_capture'
                    ? 'awaiting_capture'
                    : run?.status === 'failed'
                      ? 'failed'
                      : 'challenge_ready'
                : 'not_started';
            return {
                method,
                label:
                    method === 'hand_shaded'
                        ? 'Hand-shaded test'
                        : 'Pre-shaded test',
                state: legacyState,
                challenge_id: legacyChallenge?.challenge_id ?? null,
                challenge: legacyChallenge,
                print_url: legacyChallenge?.print_url ?? null,
                required_page_count:
                    Number(legacyChallenge?.sheet_pages?.length) || 1,
                pages: [],
                attempts: [],
            };
        }),
    );
    const activeReviewMethodData = $derived(
        methodCards.find((methodData) => methodData.method === activeMethod) ??
            null,
    );
    const activeEvidenceAttempt = $derived(
        (activeReviewMethodData?.attempts ?? []).find(
            (attempt) =>
                Number(attempt.capture_id) ===
                Number(evidencePage?.capture_id ?? selectedCaptureId),
        ) ?? null,
    );

    const activePhysicalMethod = $derived.by(() => {
        const selected = methodCards.find(
            (candidate) => candidate.method === activeCaptureMethod,
        );
        if (selected && selected.state !== 'passed') return selected.method;

        return (
            methodCards.find((candidate) => candidate.state !== 'passed')
                ?.method ??
            selected?.method ??
            METHOD_KEYS[0]
        );
    });

    const guideMethodData = $derived(
        methodCards.find(
            (candidate) => candidate.method === (guideMethod ?? activeMethod),
        ) ?? null,
    );
    const guideChallenge = $derived(
        guideMethodData?.challenge ??
            (guideMethodData?.challenge_id ? guideMethodData : null) ??
            run?.challenge ??
            null,
    );
    const guideChallengeId = $derived(
        guideChallenge?.challenge_id ?? guideMethodData?.challenge_id ?? null,
    );
    const methodRunId = $derived(run?.run_id ?? null);

    function setGuideContext(challenge = null) {
        const challengeId = challenge?.challenge_id ?? challenge?.id ?? null;
        const matching =
            challengeId == null
                ? null
                : methodCards.find(
                      (candidate) =>
                          Number(
                              candidate.challenge_id ??
                                  candidate.challenge?.challenge_id,
                          ) === Number(challengeId),
                  );
        guideMethod =
            matching?.method ??
            challenge?.method ??
            guideMethod ??
            activeMethod;
    }
    const qualification = $derived(
        run?.physical_assurance ?? run?.qualification ?? null,
    );

    function challengeModeForMethod() {
        if (run?.mode === 'full_qualification') {
            return 'full';
        }
        if (run?.mode === 'quick_rehearsal') {
            return 'quick';
        }
        return draft?.compiled_version_id && isProfileQualified
            ? 'full'
            : 'quick';
    }

    function methodTargetPayload(methodData) {
        const challenge = methodData?.challenge ?? methodData ?? {};
        return {
            challenge_id:
                challenge.challenge_id ?? methodData?.challenge_id ?? null,
            target_hash:
                methodData?.target_hash ??
                run?.target?.hashes?.artifact ??
                null,
            plan_hash:
                methodData?.plan_hash ??
                challenge.plan_hash ??
                run?.plan?.plan_hash ??
                null,
            expected_vector_hash:
                methodData?.expected_vector_hash ??
                challenge.expected_vector_hash ??
                run?.plan?.expected_vector_hash ??
                null,
        };
    }

    function persistEvidenceUrlState() {
        if (typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        const values = {
            evidence_method: activeMethod,
            evidence_capture: evidencePage?.capture_id ?? null,
            evidence_area: evidenceArea !== 'all' ? evidenceArea : null,
            evidence_filter: evidenceFilter !== 'all' ? evidenceFilter : null,
            evidence_page: evidencePageNo > 1 ? evidencePageNo : null,
            evidence_per_page: evidencePerPage !== 25 ? evidencePerPage : null,
            evidence_tab: evidenceTab !== 'aligned' ? evidenceTab : null,
        };
        Object.entries(values).forEach(([key, value]) => {
            if (value == null || value === '') url.searchParams.delete(key);
            else url.searchParams.set(key, String(value));
        });
        window.history.replaceState(
            window.history.state,
            '',
            `${url.pathname}${url.search}${url.hash}`,
        );
    }

    function restoreEvidenceUrlState() {
        if (typeof window === 'undefined') return;
        const params = new URLSearchParams(window.location.search);
        if (METHOD_KEYS.includes(params.get('evidence_method')))
            activeMethod = params.get('evidence_method');
        if (params.get('evidence_area'))
            evidenceArea = params.get('evidence_area');
        if (params.get('evidence_filter'))
            evidenceFilter = params.get('evidence_filter');
        if (Number(params.get('evidence_page')) > 0)
            evidencePageNo = Number(params.get('evidence_page'));
        if (Number(params.get('evidence_per_page')) > 0)
            evidencePerPage = Math.min(
                50,
                Math.max(1, Number(params.get('evidence_per_page'))),
            );
        if (
            ['captured', 'aligned', 'recognition'].includes(
                params.get('evidence_tab'),
            )
        )
            evidenceTab = params.get('evidence_tab');
    }

    async function fetchEvidencePage(context = {}) {
        const method = context.method ?? activeMethod;
        const captureId = context.captureId ?? evidencePage?.capture_id ?? null;
        if (!methodRunId || !captureId) {
            evidencePage = null;
            evidenceError = null;
            return;
        }
        activeMethod = method;
        if (context.area !== undefined) evidenceArea = context.area;
        if (context.filter !== undefined) evidenceFilter = context.filter;
        if (context.page !== undefined)
            evidencePageNo = Math.max(1, Number(context.page) || 1);
        if (context.perPage !== undefined)
            evidencePerPage = Math.min(
                50,
                Math.max(1, Number(context.perPage) || 25),
            );
        const generation = ++evidenceRequestGeneration;
        evidenceLoading = true;
        evidenceError = null;
        const params = new SvelteURLSearchParams({
            method,
            capture_id: String(captureId),
            area: evidenceArea || 'all',
            page: String(evidencePageNo),
            per_page: String(evidencePerPage),
        });
        if (evidenceFilter) params.set('filter', evidenceFilter);
        persistEvidenceUrlState();
        try {
            const response = await fetch(
                `/setup/omr/templates/verification-runs/${methodRunId}/evidence?${params.toString()}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                },
            );
            const payload = await response.json().catch(() => null);
            if (generation !== evidenceRequestGeneration) return;
            if (!response.ok || !payload || typeof payload !== 'object') {
                throw new Error(
                    payload?.detail ??
                        'The private evidence page is unavailable.',
                );
            }
            evidencePage = payload;
            evidenceArea = payload.area ?? evidenceArea;
            evidenceFilter = payload.filter ?? evidenceFilter;
            evidencePageNo = Number(payload.page ?? evidencePageNo);
            evidencePerPage = Number(payload.per_page ?? evidencePerPage);
            persistEvidenceUrlState();
            if (
                evidenceFilter === 'mismatches' &&
                Number(payload.mismatch_total) === 0 &&
                Array.isArray(payload.items) &&
                payload.items.length === 0
            ) {
                await fetchEvidencePage({ filter: 'all', page: 1 });
            }
        } catch (error) {
            if (generation === evidenceRequestGeneration)
                evidenceError = {
                    detail:
                        error?.message ??
                        'The private evidence page is unavailable.',
                };
        } finally {
            if (generation === evidenceRequestGeneration)
                evidenceLoading = false;
        }
    }

    async function saveEvidenceDecision(payload, item) {
        const captureId = Number(evidencePage?.capture_id ?? 0);
        const itemId = Number(item?.id ?? item?.item_id ?? 0);
        if (!methodRunId || captureId < 1 || itemId < 1) {
            throw new Error(
                'The verification item review context is unavailable.',
            );
        }

        const response = await postJson(
            `/setup/omr/templates/verification-runs/${methodRunId}/captures/${captureId}/items/${itemId}/review`,
            payload,
        );
        await fetchEvidencePage({
            method: activeMethod,
            captureId,
            area: evidenceArea,
            filter: evidenceFilter,
            page: evidencePageNo,
            perPage: evidencePerPage,
        });

        const statusUrl =
            run?.status_url ??
            `/setup/omr/templates/verification-runs/${methodRunId}`;
        if (statusUrl) await hydrateStatus(statusUrl);

        return response;
    }

    function openMethodEvidence(context) {
        activeMethod = context.method;
        evidenceArea = 'all';
        evidenceFilter = context?.filter ?? null;
        evidencePageNo = 1;
        evidencePage = null;
        evidenceModalOpen = true;
        persistEvidenceUrlState();
        fetchEvidencePage({ ...context, filter: evidenceFilter });
    }

    function closeEvidenceModal() {
        evidenceModalOpen = false;
        evidencePage = null;
        evidenceError = null;
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            [
                'evidence_method',
                'evidence_capture',
                'evidence_area',
                'evidence_filter',
                'evidence_page',
                'evidence_per_page',
                'evidence_tab',
            ].forEach((key) => url.searchParams.delete(key));
            window.history.replaceState(
                window.history.state,
                '',
                `${url.pathname}${url.search}${url.hash}`,
            );
        }
    }

    function evidenceGroupChange(area) {
        fetchEvidencePage({ area, page: 1 });
    }

    function evidenceFilterChange(filter) {
        fetchEvidencePage({ filter, page: 1 });
    }

    function evidencePageChange(page) {
        fetchEvidencePage({ page });
    }

    function evidenceViewChange(view) {
        fetchEvidencePage(view);
    }

    function evidenceRetry() {
        fetchEvidencePage({});
    }

    function evidenceTabChange(tab) {
        evidenceTab = tab;
        persistEvidenceUrlState();
    }

    onMount(() => {
        restoreEvidenceUrlState();
        const params =
            typeof window === 'undefined'
                ? null
                : new URLSearchParams(window.location.search);
        const captureId = Number(params?.get('evidence_capture') ?? 0);
        if (captureId > 0 && methodRunId) {
            evidenceModalOpen = true;
            fetchEvidencePage({
                method: activeMethod,
                captureId,
                page: evidencePageNo,
            });
        }
    });
    $effect(() => {
        const lockVersion = Number(run?.draft_lock_version);
        if (Number.isInteger(lockVersion) && lockVersion >= 0) {
            onDraftLockVersion(lockVersion);
        }
    });
    function attachPoller(
        statusUrl,
        expectedRunId = null,
        expectedMode = null,
    ) {
        const generation = ++requestGeneration;
        const runId =
            expectedRunId ?? runOverride?.run_id ?? run?.run_id ?? null;
        if (poller) poller.stop();
        lastHydratedUrl = statusUrl;
        isHydratingOverride = false;
        poller = createVerificationPoller({
            statusUrl,
            expectedRunId: runId,
            expectedMode,
            onStatus: (status) => {
                if (
                    !status ||
                    generation !== requestGeneration ||
                    !poller?.isRunning()
                )
                    return;
                if (runId !== null && Number(status.run_id) !== Number(runId))
                    return;
                if (expectedMode !== null && status.mode !== expectedMode)
                    return;
                runOverride = mergeRunPayload(runOverride ?? run, status);
                if (failure?.code === 'status_unavailable') failure = null;
                const targetCurrent =
                    status.target_current !== false &&
                    status.target_stale !== true;
                if (
                    targetCurrent &&
                    status.mode === 'digital' &&
                    status.status === 'passed'
                ) {
                    if (
                        draft.stage !== 'compiled' &&
                        draft.compiled_version_id == null
                    ) {
                        draft.stage = 'digital_verified';
                    }
                    draft.digital_verified = true;
                    draft.digital_verification_current = true;
                    notice =
                        'Digital engine check passed: machine-filled proof and no-go matrix exact-recovery verified.';
                } else if (
                    targetCurrent &&
                    status.mode === 'digital' &&
                    status.status === 'failed'
                ) {
                    draft.digital_verified = false;
                    draft.digital_verification_current = false;
                }
            },
            onError: (message) => {
                if (generation !== requestGeneration || !poller?.isRunning())
                    return;
                failure = { code: 'status_unavailable', detail: message };
            },
        });
        poller.start();
    }

    async function hydrateStatus(statusUrl) {
        if (!statusUrl) {
            isHydratingOverride = false;
            return;
        }
        const currentUrl = statusUrl;
        lastHydratedUrl = currentUrl;
        const generation = ++requestGeneration;
        if (poller) {
            poller.stop();
            poller = null;
        }
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        isHydratingOverride = true;
        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
             });
            if (!response || typeof response.json !== 'function') return;
             const payload = await response.json().catch(() => null);
             if (
                generation !== requestGeneration ||
                lastHydratedUrl !== currentUrl
            )
                return;
            if (
                !response.ok ||
                !payload ||
                typeof payload.status !== 'string'
            ) {
                throw new Error('Verification status is unavailable.');
            }
            if (failure?.code === 'status_unavailable') failure = null;
            runOverride = mergeRunPayload(runOverride ?? run, payload);
            const targetCurrent =
                payload.target_current !== false &&
                payload.target_stale !== true;
            if (
                targetCurrent &&
                payload.mode === 'digital' &&
                payload.status === 'passed'
            ) {
                if (
                    draft.stage !== 'compiled' &&
                    draft.compiled_version_id == null
                ) {
                    draft.stage = 'digital_verified';
                }
                draft.digital_verified = true;
                draft.digital_verification_current = true;
                notice =
                    'Digital engine check passed: machine-filled proof and no-go matrix exact-recovery verified.';
            } else if (
                targetCurrent &&
                payload.mode === 'digital' &&
                payload.status === 'failed'
            ) {
                draft.digital_verified = false;
                draft.digital_verification_current = false;
            }
            if (
                !isTerminalRunStatus(payload.status) &&
                generation === requestGeneration
            ) {
                attachPoller(
                    statusUrl,
                    Number(payload.run_id) || null,
                    payload.mode ?? null,
                );
            }
        } catch (error) {
            if (
                generation === requestGeneration &&
                lastHydratedUrl === currentUrl
            ) {
                failure = {
                    code: 'status_unavailable',
                    detail: requestErrorDetail(
                        error,
                        'Verification status is unavailable.',
                    ),
                };
            }
        } finally {
            clearTimeout(timeoutId);
            if (
                generation === requestGeneration &&
                lastHydratedUrl === currentUrl
            ) {
                isHydratingOverride = false;
            }
        }
    }

    $effect(() => {
        const draftId = draft?.id ?? null;
        const currentUrl = baseRun?.status_url ?? null;
        const baseRunId = baseRun?.run_id ?? null;
        const localRunId = runOverride?.run_id ?? null;

        if (draftId !== lastDraftId) {
            lastDraftId = draftId;
            lastBaseRunId = baseRunId;
            requestGeneration += 1;
            poller?.stop();
            poller = null;
            runOverride = null;
            lastHydratedUrl = null;
            if (currentUrl) {
                isHydratingOverride = true;
                hydrateStatus(currentUrl);
            } else {
                isHydratingOverride = false;
            }
            return;
        }

        const newerLocalOverride =
            baseRunId !== null &&
            localRunId !== null &&
            Number(localRunId) > Number(baseRunId);
        if (baseRunId !== lastBaseRunId) {
            lastBaseRunId = baseRunId;
            if (newerLocalOverride) return;
            requestGeneration += 1;
            poller?.stop();
            poller = null;
            runOverride = null;
            lastHydratedUrl = null;
            if (currentUrl) {
                isHydratingOverride = true;
                hydrateStatus(currentUrl);
            } else {
                isHydratingOverride = false;
            }
        } else if (!currentUrl && localRunId === null) {
            isHydratingOverride = false;
            lastHydratedUrl = null;
        }
    });

    onDestroy(() => {
        requestGeneration += 1;
        poller?.stop();
        poller = null;
        lastHydratedUrl = null;
        lastBaseRunId = null;
    });

    function requestErrorText(value) {
        if (typeof value === 'string' && value.trim()) return value.trim();
        if (Array.isArray(value))
            return value.map(requestErrorText).find(Boolean) ?? '';
        if (value && typeof value === 'object') {
            for (const key of ['detail', 'message', 'error', 'reason']) {
                const text = requestErrorText(value[key]);
                if (text) return text;
            }
        }
        return '';
    }

    function requestErrorCode(error, fallback) {
        const candidates = [
            error?.payload?.code,
            error?.code,
            error?.payload?.error?.code,
            error?.error?.code,
        ];
        return (
            candidates
                .find(
                    (candidate) =>
                        typeof candidate === 'string' && candidate.trim(),
                )
                ?.trim() ?? fallback
        );
    }

    function requestErrorDetail(error, fallback) {
        const detail =
            requestErrorText(error) || requestErrorText(error?.payload);
        return detail || fallback;
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...csrfHeaders(getCsrfToken()),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errorPayload =
                payload && typeof payload === 'object' ? payload : {};
            const err = new Error(
                requestErrorDetail(errorPayload, 'The request was rejected.'),
            );
            const code = requestErrorCode(errorPayload, '');
            if (code) err.code = code;
            err.payload = errorPayload;
            throw err;
        }
        return payload;
    }

    async function runDigitalVerification() {
        if (lifecycleBusy || busyFlags.digital || !urls.digital_verification)
            return;
        busyFlags = { ...busyFlags, digital: true };
        failure = null;
        notice = 'Starting durable digital verification run…';
        try {
            const payload = await postJson(urls.digital_verification, {
                idempotency_key: newIdempotencyKey(),
            });
            if (!payload.status_url || !payload.run_id)
                throw new Error(
                    'Digital verification did not return a run and status URL.',
                );
            // Clear any historical physical run before the first status response
            // arrives; otherwise its awaiting_capture state can flash as checkpoint
            // two completion during the request turn.
            runOverride = mergeRunPayload(
                run,
                {
                    ...payload,
                    run_id: Number(payload.run_id),
                    status: payload.status ?? 'queued',
                    mode: 'digital',
                    target_current: payload.target_current !== false,
                    target_stale: payload.target_stale === true,
                    digital_verification_current:
                        payload.digital_verification_current === true,
                    challenge: null,
                    evidence: [],
                },
                'queued',
            );
            attachPoller(payload.status_url, Number(payload.run_id), 'digital');
            notice =
                'Digital engine check started — the worker reports real phase and variant progress.';
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'digital_start_failed'),
                detail: requestErrorDetail(
                    error,
                    'Digital verification could not be started.',
                ),
            };
            notice = '';
        } finally {
            busyFlags = { ...busyFlags, digital: false };
        }
    }

    async function retryDigital() {
        if (busyFlags.digital || !run?.run_id) return;
        busyFlags = { ...busyFlags, digital: true };
        failure = null;
        try {
            const payload = await postJson(
                `/setup/omr/templates/verification-runs/${run.run_id}/retry`,
                {},
            );
            if (payload.status_url)
                attachPoller(
                    payload.status_url,
                    Number(payload.run_id ?? run.run_id),
                    'digital',
                );
            notice = `Retry started — attempt ${payload.attempt ?? 'next'} against the same pinned plan.`;
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'retry_failed'),
                detail: requestErrorDetail(
                    error,
                    'The run could not be retried.',
                ),
            };
        } finally {
            busyFlags = { ...busyFlags, digital: false };
        }
    }

    async function retryPhysicalChallenge() {
        const currentRun = run;
        if (
            lifecycleBusy ||
            busyFlags.challenge ||
            !hasResumablePhysicalChallenge(currentRun)
        )
            return;
        const resumeStage = physicalChallengeResumeStage(currentRun.challenge);
        const fallbackStatus =
            resumeStage === 'print' ? 'awaiting_print' : 'awaiting_capture';
        busyFlags = { ...busyFlags, challenge: true };
        failure = null;
        try {
            const payload = await postJson(
                `/setup/omr/templates/verification-runs/${currentRun.run_id}/retry`,
                {},
            );
            const responseChallenge = {
                ...(currentRun.challenge ?? {}),
                ...(payload.challenge ?? {}),
            };
            const immediateRun = mergeRunPayload(
                currentRun,
                { ...payload, challenge: responseChallenge },
                fallbackStatus,
            );
            runOverride = {
                ...immediateRun,
                available_actions:
                    payload.available_actions ??
                    (fallbackStatus === 'awaiting_print'
                        ? ['print_challenge']
                        : ['submit_capture']),
            };
            expandedStepKeyOverride = 'physical_challenge';
            const statusUrl =
                payload.status_url ?? currentRun.status_url ?? null;
            if (statusUrl)
                attachPoller(
                    statusUrl,
                    Number(payload.run_id ?? currentRun.run_id),
                    currentRun.mode ?? null,
                );
            notice =
                resumeStage === 'print'
                    ? 'Existing challenge resumed — inspect the on-screen sheet & guide, or print at Actual Size before capture.'
                    : 'Existing challenge resumed — capture the sheet when ready.';
        } catch (error) {
            const code = requestErrorCode(error, 'retry_failed');
            failure = {
                code,
                detail:
                    code === 'challenge_exists_resume_required'
                        ? 'An existing challenge is ready to resume. Use the Resume challenge action instead of creating another pack.'
                        : requestErrorDetail(
                              error,
                              'The challenge could not be resumed.',
                          ),
            };
        } finally {
            busyFlags = { ...busyFlags, challenge: false };
        }
    }

    async function compileCandidate() {
        if (
            lifecycleBusy ||
            busyFlags.compile ||
            !urls.compile ||
            !isProfileQualified
        )
            return;
        busyFlags = { ...busyFlags, compile: true };
        failure = null;
        try {
            const payload = await postJson(urls.compile, {
                idempotency_key: newIdempotencyKey(),
            });
            draft.can_edit = false;
            draft.stage = 'compiled';
            draft.compiled_version_id =
                payload.template_version_id ?? draft.compiled_version_id;
            draft.compiled_version_status =
                payload.compiled_version_status ?? 'compiled';
            urls.native_template = payload.template_version_id
                ? `/setup/omr/templates/${payload.template_version_id}/native/template.json`
                : urls.native_template;
            notice =
                'Template version locked for physical testing. You can now run test captures with real paper.';
        } catch (error) {
            const code = requestErrorCode(error, '');
            const detail = requestErrorDetail(
                error,
                'Digital recognition check is missing or stale. Complete digital check before locking for testing.',
            );
            if (
                code === 'compile_gate_missing' ||
                detail.includes('compile_gate_missing')
            ) {
                expandedStepKeyOverride = 'digital';
                failure = {
                    code: 'compile_gate_missing',
                    detail,
                };
                const statusUrl =
                    baseRun?.status_url ||
                    urls.status ||
                    urls.digital_verification_status ||
                    (draft.id
                        ? `/setup/omr/templates/canonical/drafts/${draft.id}/verification-runs/latest`
                        : null);
                if (statusUrl) {
                    hydrateStatus(statusUrl);
                }
            } else {
                failure = {
                    code: requestErrorCode(error, 'compile_failed'),
                    detail: requestErrorDetail(
                        error,
                        'Failed to lock template version.',
                    ),
                };
            }
        } finally {
            busyFlags = { ...busyFlags, compile: false };
        }
    }

    async function createChallenge(method = 'pre_shaded') {
        if (lifecycleBusy || busyFlags.challenge || !draft.id) return;
        activeMethod = method;
        activeCaptureMethod = method;
        challengeMode = challengeModeForMethod();
        const methodData = methodCards.find(
            (candidate) => candidate.method === method,
        );
        if (methodData?.challenge_id || methodData?.challenge?.challenge_id) {
            activeMethod = method;
            activeCaptureMethod = method;
            expandedStepKeyOverride = 'physical_challenge';
            notice =
                'This test sheet already exists. Continue with its saved print and capture steps.';
            failure = null;
            return;
        }
        busyFlags = { ...busyFlags, challenge: true };
        failure = null;
        try {
            const payload = await postJson(
                `/setup/omr/templates/canonical/drafts/${draft.id}/challenge`,
                {
                    mode: challengeMode,
                    method,
                    idempotency_key: newIdempotencyKey(),
                },
            );
            if (payload.run_id) {
                const fallbackStatus = payload.status ?? 'awaiting_print';
                const responseMode =
                    payload.mode ??
                    (challengeMode === 'full'
                        ? 'full_qualification'
                        : 'quick_rehearsal');
                const responseChallenge = payload.challenge ?? {
                    challenge_id: payload.challenge_id,
                    challenge_code: payload.challenge_code,
                    mode: challengeMode,
                    status: 'created',
                    print_url: payload.print_url ?? null,
                    print_mime: payload.print_mime ?? 'application/pdf',
                    pdf_ready: payload.pdf_ready ?? true,
                    guide: payload.guide ?? null,
                };
                runOverride = mergeRunPayload(
                    run,
                    {
                        ...payload,
                        mode: responseMode,
                        status: fallbackStatus,
                        status_url: payload.status_url ?? null,
                        available_actions:
                            payload.available_actions ??
                            (fallbackStatus === 'awaiting_print'
                                ? ['print_challenge']
                                : ['submit_capture']),
                        challenge: responseChallenge,
                    },
                    fallbackStatus,
                );
            }
            if (payload.status_url) await hydrateStatus(payload.status_url);
            notice = isUploadedSource
                ? 'Physical test ready. Capture or upload a photo or scan of your physical test sheet.'
                : 'Test sheet created. Inspect the sheet and guide, then print at 100% / actual size.';
        } catch (error) {
            const code = requestErrorCode(error, 'challenge_failed');
            failure = {
                code,
                detail:
                    code === 'challenge_exists_resume_required'
                        ? 'A saved physical test is ready to resume. Resume challenge instead of creating another sheet.'
                        : requestErrorDetail(
                              error,
                              'The challenge pack could not be created.',
                          ),
            };
            notice = '';
        } finally {
            busyFlags = { ...busyFlags, challenge: false };
        }
    }

    async function executeChallengeRecovery(recovery) {
        if (busyFlags.challenge || !recovery?.action_url) return;
        const isRepair = recovery.kind === 'repair_reference';
        const isRestart = recovery.kind === 'restart_rehearsal';
        busyFlags = { ...busyFlags, challenge: true };
        failure = null;
        try {
            const payload = await postJson(recovery.action_url, {
                idempotency_key: newIdempotencyKey(),
            });
            if (payload.lock_version != null)
                onDraftLockVersion(payload.lock_version);
            if (
                isRestart &&
                payload.digital_reverification &&
                payload.run_id != null
            ) {
                runOverride = mergeRunPayload(run, {
                    ...payload,
                    mode: 'digital',
                    challenge: null,
                    challenge_recovery: null,
                    evidence: [],
                });
            }
            const statusUrl =
                payload.status_url ??
                (payload.run_id
                    ? `/setup/omr/templates/verification-runs/${payload.run_id}`
                    : (run?.status_url ??
                      `/setup/omr/templates/verification-runs/${run?.run_id}`));
            if (statusUrl) {
                await hydrateStatus(statusUrl);
            }
            if (isRepair) {
                setGuideContext(run?.challenge);
                notice = 'Challenge sheet updated.';
                guideModalOpen = true;
            } else if (isRestart) {
                if (
                    payload.digital_reverification ||
                    payload.challenge_id == null
                ) {
                    notice =
                        'Current layout prepared — digital verification is running. A quick rehearsal will unlock after it passes.';
                    expandedStepKeyOverride = 'digital';
                } else {
                    notice = 'New rehearsal started with the current layout.';
                    expandedStepKeyOverride = 'physical_challenge';
                }
            }
        } catch (error) {
            failure = {
                code: requestErrorCode(
                    error,
                    isRestart
                        ? 'restart_rehearsal_failed'
                        : 'repair_reference_failed',
                ),
                detail: requestErrorDetail(
                    error,
                    isRestart
                        ? 'Could not start a new rehearsal.'
                        : 'Could not update challenge sheet.',
                ),
            };
        } finally {
            busyFlags = { ...busyFlags, challenge: false };
        }
    }

    async function markPrinted(context = {}) {
        if (busyFlags.print || !run?.run_id) return;
        const method = context.method ?? activeMethod;
        activeMethod = method;
        activeCaptureMethod = method;
        busyFlags = { ...busyFlags, print: true };
        failure = null;
        try {
            const methodData = methodCards.find(
                (candidate) => candidate.method === method,
            );
            const binding = methodTargetPayload(methodData);
            const payload = await postJson(
                `/setup/omr/templates/verification-runs/${run.run_id}/print`,
                {
                    method,
                    ...binding,
                    challenge_id:
                        context.challengeId ?? binding.challenge_id ?? null,
                    idempotency_key: newIdempotencyKey(),
                },
            );
            expandedStepKeyOverride = 'capture';
            if (payload.status_url) await hydrateStatus(payload.status_url);
            notice = 'Test sheet marked printed. Capture the page when ready.';
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'print_failed'),
                detail: requestErrorDetail(
                    error,
                    'The test sheet could not be marked printed.',
                ),
            };
        } finally {
            busyFlags = { ...busyFlags, print: false };
        }
    }

    async function submitCapture(context) {
        const file = context?.file ?? context;
        const method = context?.method ?? activeMethod;
        const pageNo = Number(context?.pageNo ?? capturePageNo) || 1;
        if (busyFlags.capture || !file || !run?.run_id) return;
        activeMethod = method;
        activeCaptureMethod = method;
        capturePageNo = pageNo;
        busyFlags = { ...busyFlags, capture: true };
        failure = null;
        notice = '';
        const form = new FormData();
        const methodData = methodCards.find(
            (candidate) => candidate.method === method,
        );
        const binding = methodTargetPayload(methodData);
        form.append('image', file);
        form.append('method', method);
        form.append('page_no', String(pageNo));
        form.append('capture_source', captureSourceForFile(file));
        form.append('idempotency_key', newIdempotencyKey());
        form.append('crop_policy', context.cropPolicy ?? 'auto_document_quad');
        Object.entries(binding).forEach(([key, value]) => {
            if (value != null && value !== '') form.append(key, String(value));
        });
        if (typeof context.medium === 'string' && context.medium.trim())
            form.append('medium', context.medium);
        try {
            const response = await fetch(
                `/setup/omr/templates/verification-runs/${run.run_id}/capture`,
                {
                    method: 'POST',
                    headers: {
                        ...csrfHeaders(getCsrfToken()),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: form,
                },
            );
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                failure = payload.code === 'verification_capture_retake_required'
                    ? {
                          code: payload.code,
                          detail: payload.detail || 'This page already has analyzed evidence. Start a retake before uploading another image.',
                      }
                    : {
                          code: requestErrorCode(payload, 'capture_rejected'),
                          detail: requestErrorDetail(
                              payload,
                              'The capture was rejected by the engine worker.',
                          ),
                      };
                if (payload.status_url) await hydrateStatus(payload.status_url);
                return;
            }
            if (payload.status_url) await hydrateStatus(payload.status_url);
            notice = '';
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'network_error'),
                detail: requestErrorDetail(
                    error,
                    'The capture service is unreachable.',
                ),
            };
        } finally {
            busyFlags = { ...busyFlags, capture: false };
        }
    }

    async function retakeMethodPage(context = {}) {
        const method = context.method ?? activeMethod;
        const pageNo = Number(context.pageNo ?? capturePageNo) || 1;
        const methodData = methodCards.find(
            (candidate) => candidate.method === method,
        );
        const page = (methodData?.pages ?? []).find(
            (candidate) => Number(candidate.page_no) === pageNo,
        );
        if (
            busyFlags.capture ||
            !run?.run_id ||
            !Number.isInteger(Number(page?.page_version))
        )
            return;

        activeMethod = method;
        activeCaptureMethod = method;
        capturePageNo = pageNo;
        busyFlags = { ...busyFlags, capture: true };
        failure = null;
        notice = '';
        try {
            const payload = await postJson(
                `/setup/omr/templates/verification-runs/${run.run_id}/methods/${method}/pages/${pageNo}/retake`,
                {
                    page_version: Number(page.page_version),
                    idempotency_key: newIdempotencyKey(),
                },
            );
            if (payload.status_url) await hydrateStatus(payload.status_url);
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'retake_failed'),
                detail: requestErrorDetail(
                    error,
                    `Page ${pageNo} could not be prepared for another capture.`,
                ),
            };
        } finally {
            busyFlags = { ...busyFlags, capture: false };
        }
    }

    async function reviewCapture(decision, context = {}) {
        if (busyFlags.review || !run?.run_id) return false;
        const method = context.method ?? activeMethod;
        activeMethod = method;
        activeCaptureMethod = method;
        busyFlags = { ...busyFlags, review: true };
        failure = null;
        try {
            const payload = await postJson(
                `/setup/omr/templates/verification-runs/${run.run_id}/review`,
                {
                    decision,
                    method,
                    capture_id: context.captureId ?? selectedCaptureId ?? null,
                    note: context.note || null,
                    idempotency_key: newIdempotencyKey(),
                },
            );
            if (payload.status_url) await hydrateStatus(payload.status_url);
            notice =
                decision === 'pass'
                    ? 'Test review completed.'
                    : 'A new capture was requested; retained history is unchanged.';
            return true;
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'review_failed'),
                detail: requestErrorDetail(
                    error,
                    'The test review could not be recorded.',
                ),
            };
            return false;
        } finally {
            busyFlags = { ...busyFlags, review: false };
        }
    }

    async function completeEvidenceModalReview(decision, note) {
        if (decision !== 'pass' && decision !== 'fail') return false;
        const captureId = evidencePage?.capture_id ?? selectedCaptureId;
        const saved = await reviewCapture(decision, {
            method: activeMethod,
            captureId,
            note,
        });
        if (saved) closeEvidenceModal();
        return saved;
    }

    async function handleDiscardAttempt({
        method,
        captureId,
        pageNo,
        pageVersion,
        lockVersion,
        idempotencyKey,
        onIdempotencyKey,
    }) {
        if (!run?.run_id || !captureId || busyFlags.discard) return false;
        const methodData = methodCards.find(
            (candidate) => candidate.method === method,
        );
        const binding = methodTargetPayload(methodData);
        const page = (methodData?.pages ?? []).find(
            (candidate) => Number(candidate.page_no) === Number(pageNo),
        );
        const key = idempotencyKey || newIdempotencyKey();
        onIdempotencyKey?.(key);
        busyFlags = { ...busyFlags, discard: true };
        try {
            const discardUrl =
                typeof route === 'function' && Boolean(route().has?.('setup.omr.templates.canonical.verification_runs.captures.discard'))
                    ? route(
                          'setup.omr.templates.canonical.verification_runs.captures.discard',
                          { run: run.run_id, capture: captureId },
                      )
                    : `/setup/omr/templates/verification-runs/${run.run_id}/captures/${captureId}/discard`;
            const payload = await postJson(
                discardUrl,
                {
                    method,
                    page_no: Number(pageNo),
                    page_version: pageVersion ?? page?.page_version,
                    capture_lock_version: lockVersion,
                    ...binding,
                    idempotency_key: key,
                },
            );
            if (payload.status_url) {
                await hydrateStatus(payload.status_url);
                if (failure?.code === 'status_unavailable') {
                    throw new Error('Attempt disposition was recorded, but status refresh failed. Retry to refresh status.');
                }
            }
            notice = 'Attempt discarded. Evidence remains in history.';
            return true;
        } catch (error) {
            if (error?.payload?.status_url) {
                await hydrateStatus(error.payload.status_url);
            }
            throw error;
        } finally {
            busyFlags = { ...busyFlags, discard: false };
        }
    }

    async function selectAttempt(context) {
        const method = context?.method ?? activeMethod;
        const attempt = context?.attempt;
        const captureId = context?.captureId ?? attempt?.capture_id;
        const reason = context?.reason?.trim() ?? '';
        if (!run?.run_id || !captureId || busyFlags.review) return false;
        activeMethod = method;
        activeCaptureMethod = method;
        busyFlags = { ...busyFlags, review: true };
        failure = null;
        const methodData = methodCards.find(
            (candidate) => candidate.method === method,
        );
        const page = (methodData?.pages ?? []).find(
            (candidate) =>
                Number(candidate.page_no) ===
                Number(context.pageNo ?? attempt?.page_no),
        );
        const binding = methodTargetPayload(methodData);
        try {
            const payload = await postJson(
                `/setup/omr/templates/verification-runs/${run.run_id}/captures/${captureId}/select`,
                {
                    method,
                    page_no: Number(context.pageNo ?? attempt?.page_no ?? 1),
                    reason,
                    page_version: page?.page_version ?? null,
                    ...binding,
                    idempotency_key: newIdempotencyKey(),
                },
            );
            if (payload.status_url) await hydrateStatus(payload.status_url);
            notice = 'Selected passing evidence for this method and page.';
            return true;
        } catch (error) {
            failure = {
                code: requestErrorCode(error, 'selection_failed'),
                detail: requestErrorDetail(
                    error,
                    'The passing attempt could not be selected.',
                ),
            };
            return false;
        } finally {
            busyFlags = { ...busyFlags, review: false };
        }
    }

    function openPrint(url) {
        if (!url) return;
        window.open(url, '_blank', 'noopener,noreferrer');
    }
    function openGuide(method) {
        guideMethod = METHOD_KEYS.includes(method) ? method : activeMethod;
        guideModalOpen = true;
    }
</script>

<div
    class="text-foreground [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground space-y-4"
>
    <div>
        <h2 class="text-foreground text-sm font-bold">
            Verification &amp; Dry Run
        </h2>
        <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
            Run no-write engine and physical diagnostics, then review the
            evidence. Reloading resumes the current setup run.
        </p>
    </div>

    {#if isHydrating}
        <div
            class="flex items-center gap-2.5 rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200"
            role="status"
            aria-live="polite"
        >
            <Loader2
                class="size-4 shrink-0 animate-spin text-blue-600 dark:text-blue-400"
            />
            <span class="font-semibold">Restoring verification status…</span>
        </div>
    {/if}

    {#if notice}
        <div
            class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            {notice}
        </div>
    {/if}

    {#if failure}
        <div
            class="rounded-xl border border-red-200 bg-red-50/70 p-3 text-xs text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"
        >
            <div class="font-semibold">
                {failure.detail || 'An error occurred.'}
            </div>
            {#if failure.code && failure.code !== failure.detail}
                <details class="mt-1 text-[10px] opacity-75">
                    <summary
                        class="inline-block cursor-pointer font-mono hover:underline"
                        >Technical details</summary
                    >
                    <div class="mt-0.5 font-mono text-[10px]">
                        {failure.code}
                    </div>
                </details>
            {/if}
        </div>
    {/if}
    <!-- Checkpoints Accordion (Expands directly below the clicked item) -->
    <div class="space-y-3">
        <div class="flex items-center justify-between pb-0.5">
            <div class="flex items-center gap-2">
                <span
                    class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase"
                    >Verification Checkpoints</span
                >
                <span class="text-muted-foreground text-[10px] font-medium"
                    >({states.filter((s) => s.status === 'done').length} of {states.length}
                    complete)</span
                >
            </div>
            <button
                type="button"
                onclick={toggleAll}
                class="text-muted-foreground hover:text-foreground border-border/70 hover:bg-muted/40 focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
            >
                {#if expandedStepKey !== null}
                    <ChevronUp class="size-3" />
                    <span>Collapse all</span>
                {:else}
                    <ChevronDown class="size-3" />
                    <span>Expand active</span>
                {/if}
            </button>
        </div>

        <ol aria-label="Verification progress" class="space-y-2.5">
            {#each states as state (state.key)}
                {@const isBlocked = state.status === 'blocked'}
                {@const isDone = state.status === 'done'}
                {@const isActive = state.status === 'active'}
                {@const isPending = state.status === 'pending'}
                {@const isActionActive = state.key === activeStep?.key}
                {@const isExpanded = expandedStepKey === state.key}
                {@const isDigitalActive =
                    state.key === 'digital' && isDigitalRunning}
                <li
                    aria-current={isActionActive ? 'step' : undefined}
                    class="relative"
                >
                    <section
                        aria-labelledby={isExpanded
                            ? 'current-step-heading'
                            : undefined}
                        class="rounded-xl border transition-all {isBlocked
                            ? 'border-border/60 bg-muted/20 opacity-85'
                            : isExpanded
                              ? isActive
                                  ? 'bg-card border-blue-400/80 shadow-xs ring-1 ring-blue-300/40 dark:border-blue-800 dark:ring-blue-950/60'
                                  : isDone
                                    ? 'bg-card border-emerald-400/70 shadow-xs dark:border-emerald-800/70'
                                    : 'border-border bg-card shadow-xs'
                              : 'border-border/80 bg-card/60 hover:bg-card hover:border-border'}"
                    >
                        <!-- Step Header Row (Accordion Trigger) -->
                        <button
                            type="button"
                            onclick={() => toggleStep(state.key)}
                            disabled={isBlocked}
                            aria-expanded={isExpanded}
                            class="flex w-full items-center justify-between gap-2.5 rounded-xl p-3 text-left transition {isBlocked
                                ? 'text-muted-foreground cursor-not-allowed'
                                : 'hover:bg-muted/30 focus-visible:ring-ring cursor-pointer focus-visible:ring-2 focus-visible:outline-none'}"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <!-- Node Circle -->
                                <div
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold transition-all {isDone
                                        ? 'bg-emerald-600 text-white shadow-xs dark:bg-emerald-500'
                                        : isActive
                                          ? 'bg-slate-900 text-white shadow-xs ring-2 ring-blue-500/50 dark:bg-slate-100 dark:text-slate-900 dark:ring-blue-400/50'
                                          : isPending
                                            ? 'border border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200'
                                            : 'border-border/60 bg-muted/40 text-muted-foreground/60 border'}"
                                >
                                    {#if isDone}
                                        <Check
                                            class="size-3.5 stroke-[2.5]"
                                            aria-hidden="true"
                                        />
                                    {:else if isActive && isDigitalActive}
                                        <Loader2
                                            class="size-3.5 animate-spin text-white dark:text-slate-900"
                                            aria-hidden="true"
                                        />
                                    {:else}
                                        <span>{state.step}</span>
                                    {/if}
                                </div>

                                <span
                                    id={isExpanded
                                        ? 'current-step-heading'
                                        : undefined}
                                    class="text-xs {isActive
                                        ? 'text-foreground font-bold'
                                        : isPending
                                          ? 'text-foreground font-semibold'
                                          : isDone
                                            ? 'text-foreground font-medium'
                                            : 'text-muted-foreground/80 font-normal'} truncate"
                                >
                                    {state.label}
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-bold tracking-wide uppercase {isDone
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : isActive
                                          ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
                                          : isBlocked
                                            ? 'bg-muted text-muted-foreground'
                                            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'}"
                                >
                                    {checkpointStatusLabel(state)}
                                </span>
                                {#if !isBlocked}
                                    <span
                                        class="text-muted-foreground/60 transition-transform duration-200 {isExpanded
                                            ? 'rotate-180'
                                            : ''}"
                                    >
                                        <ChevronDown class="size-3.5" />
                                    </span>
                                {/if}
                            </div>
                        </button>

                        <!-- Concise Prerequisite Line for Blocked Steps directly below the row -->
                        {#if isBlocked && state.prerequisites.length > 0}
                            <div
                                class="text-muted-foreground -mt-0.5 flex items-start gap-1 px-3 pb-2.5 text-[11px] leading-relaxed"
                            >
                                <span
                                    class="text-foreground shrink-0 font-semibold"
                                    >Requires:</span
                                >
                                <span class="break-words"
                                    >{state.prerequisites.join(' · ')}</span
                                >
                            </div>
                        {/if}
                        <!-- Dropdown Body (Rendered directly under the clicked toggler) -->
                        {#if isExpanded}
                            <div
                                class="border-border/80 bg-muted/15 space-y-3 rounded-b-xl border-t p-3.5"
                            >
                                {#if state.key === 'physical_challenge'}
                                    <details
                                        class="border-border/70 bg-muted/20 rounded-lg border px-2.5 py-2"
                                    >
                                        <summary
                                            class="text-foreground focus-visible:ring-ring flex cursor-pointer list-none items-center justify-between gap-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <span>What this step verifies</span>
                                            <ChevronDown
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </summary>
                                        <p
                                            class="text-muted-foreground mt-2 text-[10px] leading-relaxed"
                                        >
                                            {state.purpose}
                                        </p>
                                    </details>
                                {:else}
                                    <p
                                        class="text-muted-foreground text-xs leading-relaxed"
                                    >
                                        {state.purpose}
                                    </p>
                                {/if}

                                <!-- Live Digital Progress Banner (shown when actively running digital verification) -->
                                {#if state.key === 'digital' && isDigitalRunning && run}
                                    <div
                                        class="space-y-2 rounded-xl border border-blue-200 bg-blue-50/70 p-3 dark:border-blue-900/60 dark:bg-blue-950/30"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <div
                                            class="flex items-center justify-between text-xs font-semibold text-blue-900 dark:text-blue-200"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Loader2
                                                    class="size-3.5 shrink-0 animate-spin text-blue-600 dark:text-blue-400"
                                                />
                                                <span
                                                    >Running digital
                                                    verification…</span
                                                >
                                            </div>
                                            <span
                                                class="font-mono text-[11px] text-blue-700 dark:text-blue-300"
                                            >
                                                {run?.progress?.done ?? 0} / {run
                                                    ?.progress?.total || 14} variants
                                            </span>
                                        </div>

                                        <!-- Progress Bar adhering to UI kit tokens -->
                                        <div
                                            class="h-1.5 w-full overflow-hidden rounded-full bg-blue-200/70 dark:bg-blue-900/50"
                                        >
                                            <div
                                                class="h-1.5 rounded-full bg-blue-600 transition-all duration-300 ease-out dark:bg-blue-400"
                                                style="width: {Math.max(
                                                    5,
                                                    Math.min(
                                                        100,
                                                        ((run?.progress?.done ??
                                                            0) /
                                                            (run?.progress
                                                                ?.total ||
                                                                14)) *
                                                            100,
                                                    ),
                                                )}%"
                                                role="progressbar"
                                                aria-valuenow={run?.progress
                                                    ?.done ?? 0}
                                                aria-valuemin="0"
                                                aria-valuemax={run?.progress
                                                    ?.total || 14}
                                            ></div>
                                        </div>

                                        <div
                                            class="flex items-center justify-between text-[10px] text-blue-800/80 dark:text-blue-300/80"
                                        >
                                            <span
                                                >Phase: <strong
                                                    class="text-blue-950 dark:text-blue-100"
                                                    >{phaseText(run)}</strong
                                                ></span
                                            >
                                            {#if run?.phase_detail?.current_variant}
                                                <span
                                                    >Variant: <strong
                                                        class="font-mono text-blue-950 dark:text-blue-100"
                                                        >{run.phase_detail
                                                            .current_variant}</strong
                                                    ></span
                                                >
                                            {/if}
                                        </div>
                                    </div>
                                {/if}
                                {#if state.key === 'digital' && isDigitalQueued && run}
                                    <div
                                        class="rounded-lg border border-amber-200/80 bg-amber-50/60 px-2.5 py-1.5 text-[11px] text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-200"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <p>
                                            <span class="font-semibold"
                                                >Queued:</span
                                            > waiting for the dedicated verification
                                            worker to claim this run.
                                        </p>
                                        <p class="mt-1 text-[10px] opacity-80">
                                            {run?.progress?.done ?? 0} / {run
                                                ?.progress?.total || 14} variants
                                            planned
                                        </p>
                                    </div>
                                {/if}

                                <!-- Meaningful Status / Progress / Evidence Box -->
                                {#if state.status === 'active' && state.key === 'digital' && run?.safe_issue}
                                    <div
                                        class="rounded-lg border border-red-200 bg-red-50/60 p-2.5 text-[11px] text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
                                    >
                                        <span class="font-semibold">Issue:</span
                                        >
                                        {run.safe_issue.detail ||
                                            run.safe_issue.code ||
                                            state.statusText}
                                    </div>
                                {:else if state.status === 'active' && state.statusText && state.key !== 'digital'}
                                    <div
                                        class="text-muted-foreground bg-card border-border/70 rounded-lg border p-2.5 text-[11px]"
                                    >
                                        <span
                                            class="text-foreground font-semibold"
                                            >Status:</span
                                        >
                                        {state.statusText}
                                    </div>
                                {:else if state.evidenceSummary}
                                    <div
                                        class="text-muted-foreground bg-card border-border/70 rounded-lg border p-2.5 text-[11px]"
                                    >
                                        <span
                                            class="text-foreground font-semibold"
                                            >Evidence:</span
                                        >
                                        {state.evidenceSummary}
                                    </div>
                                {/if}

                                <!-- Step 1: Digital engine check controls -->
                                {#if state.key === 'digital'}
                                    {#if state.status === 'active' && state.retryAvailable}
                                        <div
                                            class="border-border space-y-2 border-t pt-3"
                                        >
                                            <button
                                                type="button"
                                                onclick={retryDigital}
                                                disabled={busyFlags.digital ||
                                                    isHydrating}
                                                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"
                                            >
                                                {#if busyFlags.digital || isHydrating}<Loader2
                                                        class="size-3.5 animate-spin"
                                                    />{:else}<RotateCcw
                                                        class="size-3.5"
                                                    />{/if}
                                                Retry digital verification
                                            </button>
                                            <p
                                                class="text-muted-foreground text-[10px]"
                                            >
                                                Retry runs the same pinned plan
                                                and expected vector — prior
                                                variant evidence is preserved.
                                            </p>
                                        </div>
                                    {:else if state.status === 'pending' && state.actionAvailable && !isHydrating}
                                        <div
                                            class="border-border space-y-2 border-t pt-3"
                                        >
                                            <button
                                                type="button"
                                                onclick={runDigitalVerification}
                                                disabled={busyFlags.digital ||
                                                    lifecycleBusy}
                                                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-blue-700 px-3 py-2 text-[11px] font-semibold text-white hover:bg-blue-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"
                                            >
                                                {#if busyFlags.digital}<Loader2
                                                        class="size-3.5 animate-spin"
                                                    />{/if}
                                                Run digital verification
                                            </button>
                                            <p
                                                class="text-muted-foreground text-[10px]"
                                            >
                                                Runs the machine-filled proof
                                                through the recognition engine
                                                and no-go matrix.
                                            </p>
                                        </div>
                                    {/if}
                                    {#if draft.print_pdf_url && !isUploadedSource}
                                        <div class="pt-1">
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    openPrint(
                                                        draft.print_pdf_url,
                                                    )}
                                                class="border-border bg-card hover:bg-muted text-foreground inline-flex w-full items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                                            >
                                                <Printer class="size-3.5" /> Open
                                                printable candidate PDF
                                            </button>
                                        </div>
                                    {/if}
                                {/if}

                                {#if state.key === 'physical_challenge'}
                                    {#if digitalPassedCheck && !run?.challenge && !isHydrating && !recovery?.kind}
                                        <input
                                            class="sr-only w-full text-left disabled:cursor-not-allowed disabled:opacity-45"
                                            type="radio"
                                            name="physical-challenge-mode"
                                            value="quick"
                                            aria-label="Quick rehearsal"
                                            aria-checked={challengeMode ===
                                                'quick'}
                                            checked={challengeMode === 'quick'}
                                            disabled={false}
                                            onchange={() =>
                                                (challengeMode = 'quick')}
                                        />
                                        <input
                                            class="sr-only w-full text-left disabled:cursor-not-allowed disabled:opacity-45"
                                            type="radio"
                                            name="physical-challenge-mode"
                                            value="full"
                                            aria-label="Full challenge"
                                            aria-checked={challengeMode ===
                                                'full'}
                                            checked={challengeMode === 'full'}
                                            disabled={!draft.compiled_version_id ||
                                                !isProfileQualified}
                                            onchange={() =>
                                                (challengeMode = 'full')}
                                        />
                                    {/if}
                                    <div
                                        class="border-border space-y-3 border-t pt-3"
                                    >
                                        {#if digitalPassedCheck && !draft.compiled_version_id && !isHydrating && !recovery?.kind}
                                            <div
                                                class="border-border bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 text-xs shadow-xs"
                                            >
                                                <div class="max-w-xl space-y-0.5">
                                                    <p class="text-foreground font-semibold">
                                                        Digital recognition check passed
                                                    </p>
                                                    <p class="text-muted-foreground text-[11px] leading-relaxed">
                                                        Lock this template design to begin testing with real paper. It remains inactive until all paper test pages pass.
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onclick={compileCandidate}
                                                    disabled={!isProfileQualified ||
                                                        busyFlags.compile ||
                                                        lifecycleBusy}
                                                    class="focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                                                >
                                                    {#if busyFlags.compile}<Loader2
                                                            class="size-3.5 animate-spin"
                                                            aria-hidden="true"
                                                        />{/if}
                                                    Lock Version for Physical Testing
                                                </button>
                                            </div>
                                        {/if}
                                        {#if isUploadedSource}
                                            <div
                                                class="border-border bg-card space-y-1.5 rounded-xl border p-3 text-xs"
                                                role="status"
                                            >
                                                <p class="text-foreground font-semibold">
                                                    Testing with Pre-Printed Answer Sheets
                                                </p>
                                                <p class="text-muted-foreground leading-relaxed text-[11px]">
                                                    Because this is an uploaded pre-printed sheet, NEXIAM will not print new blank copies. Use your genuine supplier answer sheets (both blank sheets and hand-filled test sheets) to complete the test captures. Record the sheet sample and capture device used for each test.
                                                </p>
                                            </div>
                                        {:else}
                                        <details
                                            class="border-border/70 bg-muted/20 rounded-lg border px-2.5 py-2"
                                        >
                                            <summary
                                                class="text-foreground focus-visible:ring-ring flex cursor-pointer list-none items-center justify-between gap-2 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                                            >
                                                <span
                                                    >Physical test information</span
                                                >
                                                <ChevronDown
                                                    class="size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            </summary>
                                            {#if run?.challenge}
                                                <p
                                                    class="text-muted-foreground mt-2 text-[10px] leading-relaxed"
                                                >
                                                    <strong
                                                        class="text-foreground"
                                                        >Calibration-ID
                                                        disclaimer:</strong
                                                    > this signed calibration QR identifies
                                                    diagnostic evidence only; it never
                                                    qualifies a marker profile or
                                                    template.
                                                </p>
                                            {/if}
                                            <div
                                                class="border-border/60 mt-3 border-t pt-3 text-[11px] leading-relaxed"
                                                aria-label="Physical test order"
                                            >
                                                <p class="font-semibold">
                                                    How the two tests fit
                                                    together
                                                </p>
                                                <ol
                                                    class="mt-2 list-decimal space-y-0.5 pl-4 text-blue-800 dark:text-blue-200"
                                                >
                                                    <li>
                                                        <strong
                                                            >Pre-shaded:</strong
                                                        > create, print, then capture
                                                        or upload the machine baseline.
                                                    </li>
                                                    <li>
                                                        <strong
                                                            >Hand-shaded:</strong
                                                        > create, print, shade using
                                                        the guide, then capture or
                                                        upload it.
                                                    </li>
                                                </ol>
                                                <p
                                                    class="mt-1 text-[10px] text-blue-700 dark:text-blue-300"
                                                >
                                                    Each method card owns its
                                                    full flow. There is no
                                                    separate upload step.
                                                </p>
                                            </div>
                                        </details>
                                        {/if}
                                        {#if recovery?.kind}
                                            <div
                                                class="rounded-lg border border-amber-300/70 bg-amber-50/70 p-2.5 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200"
                                            >
                                                <p class="font-semibold">
                                                    {recovery.kind ===
                                                    'repair_reference'
                                                        ? 'Update challenge sheet'
                                                        : recovery.kind ===
                                                            'restart_rehearsal'
                                                          ? 'Layout changed'
                                                          : 'Evidence retained'}
                                                </p>
                                                <p>
                                                    {recovery.kind ===
                                                    'restart_rehearsal'
                                                        ? 'This test belongs to an older layout. Start one new rehearsal; digital verification runs first, then both physical methods unlock.'
                                                        : recovery.kind ===
                                                            'repair_reference'
                                                          ? 'Update the saved physical test sheet before printing or capturing.'
                                                          : recovery.detail}
                                                </p>
                                                {#if recovery.action_url}<button
                                                        type="button"
                                                        class="focus-visible:ring-ring mt-2 inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 font-semibold text-white focus-visible:ring-2 focus-visible:outline-none"
                                                        onclick={() =>
                                                            executeChallengeRecovery(
                                                                recovery,
                                                            )}
                                                        disabled={busyFlags.challenge ||
                                                            lifecycleBusy}
                                                        >{recovery.kind ===
                                                        'repair_reference'
                                                            ? 'Update sheet'
                                                            : 'Start new rehearsal'}</button
                                                    >{/if}
                                            </div>
                                        {/if}
                                        {#if !recovery?.kind && hasResumablePhysicalChallenge(run)}
                                            <button
                                                type="button"
                                                class="focus-visible:ring-ring inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900"
                                                onclick={retryPhysicalChallenge}
                                                disabled={busyFlags.challenge ||
                                                    lifecycleBusy}
                                            >
                                                {#if busyFlags.challenge}
                                                    <Loader2
                                                        class="size-3.5 animate-spin"
                                                        aria-hidden="true"
                                                    />
                                                {:else}
                                                    <RotateCcw
                                                        class="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                {/if}
                                                Resume challenge
                                            </button>
                                        {/if}
                                        {#if qualification?.status === 'rehearsal_only' || qualification?.blockers?.some((blocker) => blocker.code === 'candidate_not_qualification_capable')}
                                            <div
                                                class="rounded-lg border border-amber-300/70 bg-amber-50/70 p-2.5 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200"
                                                role="status"
                                            >
                                                <strong>Rehearsal only</strong> —
                                                candidate physical evidence is retained
                                                for troubleshooting and cannot qualify
                                                or activate a template.
                                            </div>
                                        {/if}
                                        {#if draft.registration_mode === 'document_bound'}
                                            <p
                                                role="status"
                                                class="text-muted-foreground text-xs"
                                            >
                                                Physical captures are diagnostic
                                                evidence. Activation still
                                                requires genuine per-page
                                                printer and device
                                                qualification.
                                            </p>
                                        {/if}
                                        {#if !recovery?.kind}
                                            <div
                                                class="flex min-w-0 flex-col gap-3"
                                                aria-label="Physical tests"
                                            >
                                                {#each methodCards as methodData (methodData.method)}
                                                    <OmrPhysicalTestMethodCard
                                                        registrationMode={draft.registration_mode}
                                                        {isUploadedSource}
                                                        method={methodData.method}
                                                        {methodData}
                                                        requiredPageCount={methodPageCount(
                                                            methodData,
                                                        )}
                                                        busy={busyFlags.challenge || busyFlags.discard ||
                                                            lifecycleBusy}
                                                        captureBusy={busyFlags.capture &&
                                                            activeMethod ===
                                                                methodData.method}
                                                        captureDisabled={isHydrating ||
                                                            !methodRunId}
                                                        captureEnabled={methodData.method ===
                                                            activePhysicalMethod}
                                                        captureQueueReason="Another physical method is active. Select this method to capture it."
                                                        phaseDetail={run?.phase_detail}
                                                        onCreate={createChallenge}
                                                        onOpenPrint={openPrint}
                                                        onOpenGuide={() =>
                                                            openGuide(
                                                                methodData.method,
                                                            )}
                                                        onMarkPrinted={markPrinted}
                                                        onSubmitCapture={submitCapture}
                                                        onOpenEvidence={openMethodEvidence}
                                                        onRetry={retakeMethodPage}
                                                        onDiscardAttempt={handleDiscardAttempt}
                                                        onSelectMethod={selectCaptureMethod}
                                                        onSelectAttempt={selectAttempt}
                                                        onAcceptReview={(context) =>
                                                            reviewCapture('pass', context)}
                                                    />
                                                {/each}
                                            </div>
                                        {/if}
                                    </div>
                                {/if}

                            </div>
                        {/if}
                    </section>
                </li>
            {/each}
        </ol>
    </div>

    <!-- Sticky Bottom Navigation Footer -->
    <div
        class="bg-card/95 border-border sticky bottom-0 mt-3 flex flex-col gap-2 border-t pt-3 pb-1 shadow-xs backdrop-blur-xs"
    >
        {#if draft?.id}
            <a
                href={`/setup/exam-materials/answer-sheets/${draft.id}/export`}
                download
                class="border-border bg-background hover:bg-muted text-foreground inline-flex min-h-9 items-center justify-center rounded-xl border px-3 text-xs font-semibold"
            >
                Export Bundle (.zip)
            </a>
        {/if}
        {#if kit_context?.back_url}
            <button
                type="button"
                onclick={onBackToKit}
                disabled={lifecycleBusy}
                class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
            >
                <Check class="size-3.5" />
                <span>Back to {kit_context.code}</span>
            </button>
        {:else if isComplete}
            <Link
                href="/setup/exam-materials/answer-sheets"
                class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
            >
                <Check class="size-3.5" />
                <span>Finish & return to Answer Sheets</span>
            </Link>
        {/if}
        <button
            type="button"
            onclick={onBack}
            class="border-border hover:bg-muted text-foreground focus-visible:ring-ring flex w-full items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
        >
            <ChevronLeft class="size-3.5" />
            <span>Return to Recognition Mapping</span>
        </button>
    </div>

    <StudioShadingGuideModal
        bind:open={guideModalOpen}
        challenge={guideChallenge}
        method={guideMethod ?? guideMethodData?.method ?? activeMethod}
        challengeId={guideChallengeId}
        {isUploadedSource}
        onMarkPrinted={markPrinted}
        onOpenPrintPdf={openPrint}
        busy={busyFlags.print}
    />

    <OmrDiagnosticEvidenceModal
        bind:open={evidenceModalOpen}
        page={evidencePage}
        loading={evidenceLoading}
        error={evidenceError}
        onRetry={evidenceRetry}
        onPageChange={evidencePageChange}
        onGroupChange={evidenceGroupChange}
        onFilterChange={evidenceFilterChange}
        onTabChange={evidenceTabChange}
        onViewChange={evidenceViewChange}
        initialTab={evidenceTab}
        onClose={closeEvidenceModal}
        reviewable={evidencePage?.reviewable === true}
        reviewReasons={evidenceReviewReasons}
        onSaveDecision={saveEvidenceDecision}
        onSaveAndNext={saveEvidenceDecision}
        captureReviewRequired={
            activeEvidenceAttempt?.actual === 'answer_vector_mismatch'
                && activeEvidenceAttempt?.gates_pass === true
                && activeEvidenceAttempt?.failure_code === 'capture_needs_review'
                && (activeEvidenceAttempt?.needs_review_count > 0
                    || Number(activeEvidenceAttempt?.machine_needs_review_count ?? 0) > 0)
                && activeEvidenceAttempt?.attempt_state === 'failed'
                && activeEvidenceAttempt?.status === 'fail'
                && !activeEvidenceAttempt?.is_discarded
                && !activeEvidenceAttempt?.superseded_at}
        canAcceptReview={Boolean(activeEvidenceAttempt?.can_accept_reviewed)}
        captureReviewBusy={busyFlags.review}
        onCaptureDecision={completeEvidenceModalReview}
        title={`${isUploadedSource && activeMethod === 'pre_shaded' ? 'Blank supplier baseline' : activeMethod === 'hand_shaded' ? 'Hand-shaded test' : 'Pre-shaded test'} evidence`}
        subtitle={isUploadedSource && activeMethod === 'pre_shaded'
            ? 'No answer key: mapped question rows are expected to be unmarked.'
            : 'Review detected marks against the expected diagnostic result'}
    />

</div>
