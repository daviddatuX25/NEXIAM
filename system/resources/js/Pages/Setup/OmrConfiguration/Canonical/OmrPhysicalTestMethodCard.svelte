<script>
    import { untrack } from 'svelte';
    import {
        AlertTriangle,
        BookOpen,
        Check,
        Eye,
        FileText,
        Info,
        Loader2,
        Printer,
        RefreshCw,
        Trash2,
        Upload,
    } from 'lucide-svelte';
    import OmrCaptureInput from '@/Components/Omr/Diagnostics/OmrCaptureInput.svelte';
    import {
        getPipelineActivityDetail,
        getPipelinePhaseCode,
        getPipelinePhaseLabel,
        getPipelinePhaseSteps,
    } from '@/lib/omr/omrPipelineStatus';
    let {
        method = 'pre_shaded',
        registrationMode = 'fiducial_anchored',
        isUploadedSource = false,
        methodData = null,
        requiredPageCount = 1,
        busy = false,
        captureBusy = false,
        captureDisabled = false,
        captureEnabled = true,
        captureQueueReason = null,
        capturePage = 1,
        phaseDetail = null,
        onCreate = null,
        onOpenPrint = null,
        onOpenGuide = null,
        onMarkPrinted = null,
        onSubmitCapture = null,
        onOpenEvidence = null,
        onRetry = null,
        onResumeCapture = null,
        onSelectMethod = null,
        onSelectAttempt = null,
        onDiscardAttempt = null,
        onAcceptReview = null,
    } = $props();

    let selectionReason = $state('');
    let replacementAttemptId = $state(null);
    let discardAttemptId = $state(null);
    let discardBusy = $state(false);
    let discardError = $state('');
    let discardKeys = new Map();
    function beginDiscard(attempt) {
        if (!attempt?.can_discard || !onDiscardAttempt || busy) return;
        discardAttemptId = attempt.capture_id;
        discardError = '';
        if (!discardKeys.has(attempt.capture_id)) {
            discardKeys.set(attempt.capture_id, null);
        }
    }
    function cancelDiscard() {
        if (discardBusy) return;
        discardAttemptId = null;
        discardError = '';
    }
    async function confirmDiscard(attempt) {
        if (discardBusy || !onDiscardAttempt) return;
        discardBusy = true;
        discardError = '';
        try {
            const discarded = await onDiscardAttempt({
                method,
                captureId: attempt.capture_id,
                pageNo: attempt.page_no,
                pageVersion: matchingPageForAttempt(attempt)?.page_version,
                lockVersion: attempt.lock_version,
                idempotencyKey: discardKeys.get(attempt.capture_id),
                onIdempotencyKey: (key) =>
                    discardKeys.set(attempt.capture_id, key),
            });
            if (discarded) {
                discardAttemptId = null;
                discardKeys.delete(attempt.capture_id);
            }
        } catch (error) {
            discardError = error?.message ?? 'The attempt could not be discarded.';
        } finally {
            discardBusy = false;
        }
    }
    function firstActionablePage(pages) {
        return (
            pages.find((page) => page.state === 'awaiting_capture') ??
            pages.find((page) =>
                ['needs_review', 'failed'].includes(page.state),
            ) ??
            null
        );
    }
    function resolveInitialPage() {
        const pages = Array.isArray(methodData?.pages) ? methodData.pages : [];
        const actionablePage = firstActionablePage(pages);
        if (actionablePage) return Number(actionablePage.page_no);
        if (capturePage != null && Number(capturePage) >= 1) {
            return Number(capturePage);
        }
        return 1;
    }
    let selectedPage = $state(resolveInitialPage());
    let cropPolicy = $state('auto_document_quad');
    let showMethodInfo = $state(false);

    const isHand = $derived(method === 'hand_shaded');
    const isUploaded = $derived(
        Boolean(isUploadedSource),
    );
    const data = $derived(methodData ?? {});
    function latestAttemptCropPolicy(pageNo = null) {
        const candidates = Array.isArray(data.attempts) ? data.attempts : [];
        let latest = null;
        for (const attempt of candidates) {
            if (attempt.is_discarded) continue;
            if (pageNo !== null && Number(attempt.page_no) !== Number(pageNo))
                continue;
            if (
                !latest ||
                Number(attempt.capture_id) > Number(latest.capture_id)
            ) {
                latest = attempt;
            }
        }
        return latest?.crop_policy ?? null;
    }
    const latestLifecycleAttempt = $derived.by(() => {
        const candidates = Array.isArray(data.attempts) ? data.attempts : [];
        let latest = null;
        for (const attempt of candidates) {
            if (attempt.is_discarded || isSupersededAttempt(attempt)) continue;
            if (!latest || Number(attempt.capture_id) > Number(latest.capture_id)) {
                latest = attempt;
            }
        }
        return latest;
    });
    const latestLifecycleAttemptState = $derived(
        attemptLifecycleState(latestLifecycleAttempt),
    );
    const isActiveCapture = $derived(
        ['active', 'queued'].includes(latestLifecycleAttemptState),
    );
    const state = $derived(
        isActiveCapture ? 'analyzing' : (data.state ?? 'not_started'),
    );
    const challenge = $derived(
        data.challenge ?? (data.challenge_id ? data : null),
    );
    const challengeId = $derived(
        Number(challenge?.challenge_id ?? data.challenge_id) > 0
            ? Number(challenge?.challenge_id ?? data.challenge_id)
            : null,
    );
    const canResume = $derived(Boolean(challengeId));
    const canResumeCapture = $derived(
        state === 'awaiting_capture' &&
            captureEnabled &&
            canResume &&
            typeof onResumeCapture === 'function',
    );
    const scopedRunPhaseDetail = $derived(
        !phaseDetail?.method || phaseDetail.method === method
            ? phaseDetail
            : null,
    );
    const activePhaseDetail = $derived(
        state === 'failed'
            ? (data.phase_detail ?? scopedRunPhaseDetail)
            : (scopedRunPhaseDetail ?? data.phase_detail ?? null),
    );
    const analysisCropPolicy = $derived(
        activePhaseDetail?.crop_policy ??
            latestAttemptCropPolicy(selectedPage) ??
            latestAttemptCropPolicy() ??
            cropPolicy,
    );
    const activePhaseCode = $derived(getPipelinePhaseCode(activePhaseDetail));
    const activePhaseLabel = $derived(
        getPipelinePhaseLabel(
            activePhaseCode,
            registrationMode,
            analysisCropPolicy,
        ),
    );
    const failedPhaseCode = $derived(
        typeof activePhaseDetail?.failed_phase_code === 'string'
            ? activePhaseDetail.failed_phase_code
            : null,
    );
    const isAnalysisFailure = $derived(
        !isActiveCapture &&
            ((state === 'failed' && !isNeedsReview) ||
                (state === 'analyzing' && data.run_status === 'failed')),
    );
    const analysisSteps = $derived(
        getPipelinePhaseSteps(
            activePhaseCode,
            failedPhaseCode,
            registrationMode,
            analysisCropPolicy,
        ),
    );
    const analysisProgressPct = $derived.by(() => {
        const activeStepIndex = analysisSteps.findIndex(
            (step) => step.state === 'current',
        );

        if (activeStepIndex >= 0) {
            if (activeStepIndex <= 1) return 25;
            if (activeStepIndex <= 3) return 52;
            if (activeStepIndex <= 5) return 78;
            return 94;
        }

        const completedStepCount = analysisSteps.filter(
            (step) => step.state === 'complete',
        ).length;

        return Math.min(
            94,
            Math.max(
                25,
                Math.round((completedStepCount / analysisSteps.length) * 100),
            ),
        );
    });

    const targetKind = $derived(
        typeof data.target_kind === 'string' && data.target_kind.trim()
            ? data.target_kind.trim()
            : null,
    );
    const targetId = $derived(
        Number.isInteger(Number(data.target_id)) && Number(data.target_id) > 0
            ? Number(data.target_id)
            : null,
    );
    const targetHash = $derived(
        typeof data.target_hash === 'string' && data.target_hash.trim()
            ? data.target_hash.trim()
            : null,
    );
    const planHash = $derived(
        typeof data.plan_hash === 'string' && data.plan_hash.trim()
            ? data.plan_hash.trim()
            : typeof challenge?.plan_hash === 'string' &&
                challenge.plan_hash.trim()
              ? challenge.plan_hash.trim()
              : null,
    );

    const storageScope = $derived(
        targetKind &&
            targetId &&
            targetHash &&
            method &&
            challengeId &&
            planHash
            ? `omr_prep_${targetKind}_${targetId}_${targetHash}_${method}_${challengeId}_${planHash}`
            : null,
    );

    const defaultPrepState = () => ({ printOpened: false, guideOpened: false });
    function parsePrepState(raw) {
        if (!raw) return defaultPrepState();
        try {
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed))
                return defaultPrepState();
            return {
                printOpened: parsed.printOpened === true,
                guideOpened: parsed.guideOpened === true,
            };
        } catch {
            return defaultPrepState();
        }
    }

    let prepState = $state(defaultPrepState());

    $effect(() => {
        if (typeof window === 'undefined' || !storageScope) {
            prepState = defaultPrepState();
            return;
        }
        try {
            prepState = parsePrepState(localStorage.getItem(storageScope));
        } catch {
            prepState = defaultPrepState();
        }
    });

    function recordPrep(key) {
        if (!['printOpened', 'guideOpened'].includes(key)) return;
        prepState = { ...prepState, [key]: true };
        if (typeof window !== 'undefined' && storageScope) {
            try {
                localStorage.setItem(storageScope, JSON.stringify(prepState));
            } catch {
                // localStorage blocked degrades gracefully
            }
        }
    }

    function handleOpenPrint(url) {
        recordPrep('printOpened');
        onOpenPrint?.(url);
    }

    function handleOpenGuide() {
        recordPrep('guideOpened');
        onOpenGuide?.();
    }

    const pageList = $derived(
        Array.isArray(data.pages) && data.pages.length > 0
            ? data.pages
            : Array.from(
                  { length: Math.max(1, Number(requiredPageCount) || 1) },
                  (_, index) => ({
                      page_no: index + 1,
                      state: 'not_started',
                      selected_attempt: null,
                      selected_attempt_id: null,
                  }),
              ),
    );
    const firstAwaitingPage = $derived(
        pageList.find((page) => page.state === 'awaiting_capture')?.page_no ?? 1,
    );
    const selectedPageData = $derived(
        pageList.find((page) => Number(page.page_no) === Number(selectedPage)) ?? {
            page_no: selectedPage,
            state: 'not_started',
        },
    );
    const isPageAwaiting = $derived(
        selectedPageData.state === 'awaiting_capture' ||
            (state === 'challenge_ready' && selectedPageData.state === 'not_started'),
    );
    const isPageInReview = $derived(
        selectedPageData.state === 'needs_review' || selectedPageData.state === 'failed',
    );
    const pages = $derived(pageList);
    const selectedPageEntry = $derived(
        pages.find((page) => Number(page.page_no) === Number(selectedPage)) ?? null,
    );
    const selectedPageRecord = $derived(
        (Array.isArray(data.pages) ? data.pages : []).find(
            (page) => Number(page.page_no) === Number(selectedPage),
        ) ?? null,
    );
    const selectedPageNeedsRetake = $derived(
        selectedPageEntry != null &&
            ['needs_review', 'failed'].includes(selectedPageEntry.state),
    );
    let pageExplicitlySelected = $state(false);
    $effect(() => {
        if (pageExplicitlySelected || pages.length === 0) return;
        const actionablePage = firstActionablePage(pages);
        if (
            actionablePage &&
            Number(selectedPage) !== Number(actionablePage.page_no)
        ) {
            selectedPage = Number(actionablePage.page_no);
        }
    });
    const attempts = $derived(
        (Array.isArray(data.attempts) ? data.attempts : []).filter(
            (attempt) => !attempt.is_discarded,
        ),
    );
    const discardedAttempts = $derived(
        Array.isArray(data.discarded_attempts)
            ? data.discarded_attempts
            : (data.attempts ?? []).filter((attempt) => attempt.is_discarded),
    );
    function isSupersededAttempt(attempt) {
        return (
            attempt?.attempt_state === 'superseded' ||
            attempt?.superseded === true ||
            Boolean(attempt?.superseded_at)
        );
    }
    function attemptLifecycleState(attempt) {
        const explicitState =
            typeof attempt?.attempt_state === 'string'
                ? attempt.attempt_state.trim()
                : '';
        if (explicitState) return explicitState;
        if (attempt?.status === 'fail') return 'failed';
        if (attempt?.status === 'pass') return 'passed';
        return 'unknown';
    }
    function isExactPass(attempt) {
        return attempt?.attempt_state === 'passed' && attempt?.status === 'pass' &&
            attempt?.actual === 'pass' && attempt?.gates_pass === true &&
            attempt?.vector_match === true && attempt?.failure_code == null;
    }

    const retakePage = $derived.by(() => {
        const actionablePage = pages.find((page) => page.state === 'failed') ??
            pages.find((page) => page.state === 'needs_review');
        return Number(actionablePage?.page_no ?? selectedPage) || 1;
    });
    const unselectedPasses = $derived(
        attempts.filter(
            (attempt) =>
                isExactPass(attempt) &&
                !isSupersededAttempt(attempt) &&
                !isAttemptSelected(attempt),
        ),
    );
    const firstAcceptancePasses = $derived(
        unselectedPasses.filter(
            (attempt) => !pageHasSelection(matchingPageForAttempt(attempt)),
        ),
    );
    const printUrl = $derived(
        data.print_url ?? data.challenge?.print_url ?? null,
    );
    const hasGuide = $derived(
        Boolean(
            onOpenGuide &&
            (data.challenge_id ?? data.challenge?.challenge_id ?? printUrl),
        ),
    );

    const canCreate = $derived(
        captureEnabled &&
            !canResume &&
            (state === 'not_started' || state === 'failed'),
    );
    const canCapture = $derived(
        captureEnabled &&
            !isActiveCapture &&
            (state === 'awaiting_capture' ||
                (isPageInReview && ['failed', 'needs_review'].includes(state))),
    );
    const isCaptureQueued = $derived(
        state === 'awaiting_capture' && !captureEnabled,
    );
    const canSelectMethod = $derived(
        typeof onSelectMethod === 'function' &&
            !captureEnabled &&
            state !== 'passed' &&
            ![
                'awaiting_capture',
                'analyzing',
                'needs_review',
                'failed',
            ].includes(state),
    );
    const isAnalyzing = $derived(
        state === 'analyzing' &&
            (isActiveCapture || data.run_status !== 'failed'),
    );
    const currentActivityDetail = $derived(
        isAnalyzing && !isActiveCapture
            ? getPipelineActivityDetail(
                  activePhaseCode,
                  registrationMode,
                  analysisCropPolicy,
              )
            : null,
    );
    const isRunWideFailure = $derived(data.run_status === 'failed');
    const failure = $derived(
        data.failure && typeof data.failure === 'object' ? data.failure : null,
    );
    const failureWhatHappened = $derived(
        typeof failure?.what_happened === 'string'
            ? failure.what_happened
            : isRunWideFailure
              ? 'The asynchronous verification run stopped before this method completed.'
              : 'Capture analysis stopped before the sheet could be verified.',
    );
    const failureObservation = $derived(
        typeof failure?.observation === 'string'
            ? failure.observation
            : typeof data.safe_error === 'string'
              ? data.safe_error
              : 'The verification service did not provide a structured diagnosis.',
    );
    const failureRecommendedFix = $derived(
        typeof failure?.recommended_fix === 'string'
            ? failure.recommended_fix
            : 'Retake the page. Ask an administrator to inspect the technical details if the problem repeats.',
    );
    const failureTechnical = $derived(
        failure?.technical_details &&
            typeof failure.technical_details === 'object'
            ? failure.technical_details
            : {},
    );
    const failureTechnicalEntries = $derived(
        (() => {
            const raw = Object.entries(failureTechnical).filter(
                ([, value]) =>
                    value !== null && value !== undefined && value !== '',
            );
            const seenCodes = new Set();
            return raw.filter(([label, value]) => {
                const strVal = String(value);
                if (label === 'error_code' || label === 'failure_code') {
                    if (seenCodes.has(strVal)) return false;
                    seenCodes.add(strVal);
                }
                return true;
            });
        })(),
    );
    function isReviewPending(attempt) {
        if (
            !attempt ||
            attempt.is_discarded ||
            isSupersededAttempt(attempt) ||
            !['failed', 'passed'].includes(attemptLifecycleState(attempt))
        )
            return false;
        const lifecycleState = attemptLifecycleState(attempt);
        return lifecycleState === 'failed' &&
            attempt.actual === 'answer_vector_mismatch' &&
            attempt.gates_pass === true &&
            attempt.failure_code === 'capture_needs_review' &&
            (reviewCount(attempt) > 0 || machineReviewCount(attempt) > 0);
    }

    function latestAttemptForPage(pageNo = null) {
        let latest = null;
        for (const attempt of attempts) {
            if (pageNo !== null && Number(attempt.page_no) !== Number(pageNo)) continue;
            if (!latest || Number(attempt.capture_id) > Number(latest.capture_id)) latest = attempt;
        }
        return latest;
    }

    function reviewCount(attempt) {
        const value = attempt?.needs_review_count;
        return typeof value === 'number' && Number.isInteger(value) && value >= 0 ? value : null;
    }

    function machineReviewCount(attempt) {
        const count = Number(attempt?.machine_needs_review_count ?? 0);
        return Number.isFinite(count) && count > 0 ? count : 0;
    }
    function canAcceptReviewedAttempt(attempt) {
        return Boolean(
            attempt?.can_accept_reviewed ??
                (reviewCount(attempt) === 0 && machineReviewCount(attempt) > 0),
        );
    }

    function evidenceButtonLabel(attempt) {
        if (!isReviewPending(attempt)) return 'Inspect evidence';
        const count = reviewCount(attempt);
        if (count !== null && count > 0) return `Review evidence (${count})`;
        if (count === 0 && machineReviewCount(attempt) > 0)
            return 'Review evidence (reviewed)';
        return 'Review evidence';
    }

    const latestPageAttempt = $derived(latestAttemptForPage(selectedPage));
    const latestMethodAttempt = $derived(latestAttemptForPage());
    const selectedPageAllowsReview = $derived(
        selectedPageRecord?.state == null ||
            ['needs_review', 'failed'].includes(selectedPageRecord.state),
    );
    const latestMethodAttemptPage = $derived(
        (Array.isArray(data.pages) ? data.pages : []).find(
            (page) =>
                Number(page.page_no) ===
                Number(latestMethodAttempt?.page_no),
        ) ?? null,
    );
    const latestMethodAttemptAllowsReview = $derived(
        latestMethodAttemptPage?.state == null ||
            ['needs_review', 'failed'].includes(latestMethodAttemptPage.state),
    );
    const reviewAttempt = $derived(
        !isActiveCapture
            ? (selectedPageAllowsReview && isReviewPending(latestPageAttempt)
                ? latestPageAttempt
                : state === 'needs_review' &&
                    latestMethodAttemptAllowsReview &&
                    isReviewPending(latestMethodAttempt)
                  ? latestMethodAttempt
                  : null)
            : null,
    );
    const latestHardFailure = $derived(
        latestMethodAttempt !== null &&
            !isReviewPending(latestMethodAttempt) &&
            attemptLifecycleState(latestMethodAttempt) === 'failed',
    );
    const isNeedsReview = $derived(
        !isRunWideFailure && !latestHardFailure && reviewAttempt !== null,
    );

    const stateLabel = $derived(
        isNeedsReview ? 'Review required' : ({
            not_started: 'Ready',
            challenge_ready: 'Sheet ready',
            awaiting_capture: 'Awaiting capture',
            analyzing: 'Analyzing',
            needs_review: 'Awaiting acceptance',
            passed: 'Passed',
            failed: 'Failed',
        }[state] ?? state),
    );

    const stateTone = $derived(
        isNeedsReview
            ? 'border-amber-300 bg-amber-50/70 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200'
            : ({
                  passed: 'border-emerald-300 bg-emerald-50/70 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200',
                  failed: 'border-rose-300 bg-rose-50/70 text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200',
                  needs_review: 'border-border bg-muted/40 text-foreground',
                  analyzing:
                      'border-blue-300 bg-blue-50/70 text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200',
                  awaiting_capture:
                      'border-sky-300 bg-sky-50/70 text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200',
                  challenge_ready: 'border-border bg-muted/40 text-foreground',
              }[state] ?? 'border-border bg-muted/20 text-muted-foreground'),
    );

    function pageSelectionId(page) {
        return (
            page?.selected_attempt_id ??
            page?.selected_attempt?.capture_id ??
            null
        );
    }

    function pageHasSelection(page) {
        return pageSelectionId(page) != null;
    }

    function sameCapture(first, second) {
        return (
            first != null && second != null && String(first) === String(second)
        );
    }

    function matchingPageForAttempt(attempt) {
        return (
            pages.find((page) => sameCapture(page.page_no, attempt?.page_no)) ??
            null
        );
    }

    function attemptPageAllowsReview(attempt) {
        const page = (Array.isArray(data.pages) ? data.pages : []).find(
            (candidate) =>
                Number(candidate.page_no) === Number(attempt?.page_no),
        );
        return (
            page == null ||
            page.state == null ||
            ['needs_review', 'failed'].includes(page.state)
        );
    }

    function evidenceFilterForAttempt(attempt) {
        if (!isReviewPending(attempt) || !attemptPageAllowsReview(attempt))
            return null;
        return reviewCount(attempt) === 0 && machineReviewCount(attempt) > 0
            ? 'reviewed'
            : 'needs_review';
    }

    function isAttemptSelected(attempt) {
        return pages.some((page) =>
            sameCapture(pageSelectionId(page), attempt?.capture_id),
        );
    }

    function pageAttempt(page) {
        return (
            page.selected_attempt ??
            (pageHasSelection(page)
                ? { capture_id: page.selected_attempt_id }
                : null)
        );
    }

    function attemptLabel(attempt) {
        return `Attempt ${attempt.attempt_no ?? attempt.attempt ?? '—'} · page ${attempt.page_no ?? '—'}`;
    }

    function submit(file) {
        if (!file || !onSubmitCapture || isPageInReview || !isPageAwaiting) return;
        onSubmitCapture({
            file,
            method,
            pageNo: selectedPage,
            cropPolicy,
        });
    }

    function acceptFirstAttempt(attempt, pageNo) {
        if (!onSelectAttempt) return;
        onSelectAttempt({ attempt, method, pageNo, reason: '' });
    }

    function isReplacementCandidate(attempt) {
        return (
            pageHasSelection(matchingPageForAttempt(attempt)) &&
            !isAttemptSelected(attempt) &&
            !isSupersededAttempt(attempt) &&
            isExactPass(attempt)
        );
    }

    function beginReplacement(attempt) {
        if (!isReplacementCandidate(attempt)) return;
        replacementAttemptId = attempt.capture_id ?? null;
        selectionReason = '';
    }

    function cancelReplacement() {
        replacementAttemptId = null;
        selectionReason = '';
    }

    function replaceAttempt(attempt, pageNo) {
        if (
            !onSelectAttempt ||
            !sameCapture(replacementAttemptId, attempt?.capture_id) ||
            !selectionReason.trim()
        )
            return;
        onSelectAttempt({
            attempt,
            method,
            pageNo,
            reason: selectionReason.trim(),
        });
        cancelReplacement();
    }
</script>

<article
    class="border-border/80 bg-card flex min-w-0 flex-col gap-3 rounded-xl border p-3.5 shadow-xs"
    data-omr-method={method}
>
    <header class="flex min-w-0 items-start justify-between gap-2.5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5">
                <h3 class="text-foreground truncate text-xs font-bold">
                    {isUploaded
                        ? (isHand ? '2. Hand-marked test sheet' : '1. Blank baseline sheet')
                        : (isHand ? '2. Hand-shaded test' : '1. Pre-shaded test')}
                </h3>
                <button
                    type="button"
                    onclick={() => (showMethodInfo = !showMethodInfo)}
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex items-center rounded p-0.5 transition focus-visible:ring-2 focus-visible:outline-none"
                    aria-label={isUploaded
                        ? (isHand ? 'Hand-marked supplier test info' : 'Blank supplier baseline info')
                        : (isHand ? 'Hand-shaded test info' : 'Pre-shaded test info')}
                    title={isUploaded
                        ? (isHand ? 'Hand-marked supplier test info' : 'Blank supplier baseline info')
                        : (isHand ? 'Hand-shaded test info' : 'Pre-shaded test info')}
                >
                    <Info class="size-3.5" aria-hidden="true" />
                </button>
                {#if isHand}
                    <span
                        class="rounded-full bg-sky-100 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-sky-800 uppercase dark:bg-sky-950/60 dark:text-sky-300"
                        >Human marks</span
                    >
                {:else}
                    <span
                        class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-emerald-800 uppercase dark:bg-emerald-950/60 dark:text-emerald-300"
                        >{isUploaded ? 'Clean paper' : 'Machine baseline'}</span
                    >
                {/if}
            </div>
            {#if showMethodInfo}
                <div
                    class="border-border/70 bg-muted/15 mt-2 rounded-lg border p-2.5 text-[10px] leading-relaxed"
                >
                    <p class="text-muted-foreground">
                        {#if isUploaded}
                            {isHand
                                ? 'Sample supplier sheet with answers marked in pencil or pen.'
                                : 'Clean unmarked supplier sheet to verify alignment and check for false marks.'}
                        {:else if isHand}
                            Hand-marked test sheet with sample answers filled using pencil or pen.
                        {:else}
                            Pre-marked baseline sheet tested at 100% actual scale.
                        {/if}
                    </p>
                    {#if isHand}
                        <div
                            class="mt-2 rounded-md border border-sky-200/80 bg-sky-50/70 p-2 text-[10px] leading-relaxed text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/25 dark:text-sky-200"
                        >
                            <strong>Marking guide:</strong> Mark sample answers with an HB/#2 pencil or black pen, then photograph or scan the sheet.
                        </div>
                    {:else}
                        <div
                            class="mt-2 rounded-md border border-emerald-200/80 bg-emerald-50/70 p-2 text-[10px] leading-relaxed text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200"
                        >
                            <strong>Baseline test:</strong> Verifies that sheet corners, paper alignment, and answer bubbles are detected cleanly without distortion.
                        </div>
                    {/if}
                </div>
            {/if}
        </div>
        <span
            class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold {stateTone}"
            aria-label={`Method status: ${stateLabel}`}>{stateLabel}</span
        >
    </header>

    <div
        class="flex flex-wrap items-center gap-1.5"
        aria-label={`${isHand ? 'Hand-shaded' : 'Pre-shaded'} test pages`}
    >
        {#each pages as page (page.page_no)}
            {@const selected = pageAttempt(page)}
            <span
                class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] {selected
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200'
                    : page.state === 'passed'
                      ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200'
                      : 'border-border bg-background text-muted-foreground'}"
            >
                {#if selected}<Check class="size-3" aria-hidden="true" />{/if}
                Page {page.page_no}: {selected
                    ? 'selected pass'
                    : (page.state ?? 'not started')}
            </span>
        {/each}
    </div>

    <div class="flex flex-wrap items-center gap-2">
        {#if canCreate && onCreate}
            <button
                type="button"
                aria-label={isHand
                    ? 'Create hand-shaded test sheet'
                    : 'Create pre-shaded test sheet'}
                class="focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200"
                onclick={() => onCreate(method)}
                disabled={busy}
            >
                {#if busy}<Loader2
                        class="size-3.5 animate-spin"
                        aria-hidden="true"
                    />{:else}<FileText
                        class="size-3.5"
                        aria-hidden="true"
                    />{/if}
                {isUploaded ? 'Start physical test' : 'Create test sheet'}
            </button>
        {/if}
        {#if printUrl && !isUploaded}
            <button
                type="button"
                aria-label="Open print PDF"
                class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"
                onclick={() => handleOpenPrint(printUrl)}
                disabled={busy}
            >
                 <Printer class="size-3.5" aria-hidden="true" />
                Print challenge
                {#if prepState.printOpened}
                    <span
                        class="rounded bg-emerald-100 px-1 py-0.5 text-[9px] font-bold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300"
                        >Print opened ✓</span
                    >
                {/if}
            </button>
        {/if}
        {#if hasGuide}
            <button
                type="button"
                aria-label="View shading guide"
                class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"
                onclick={handleOpenGuide}
                disabled={busy}
            >
                 <BookOpen class="size-3.5" aria-hidden="true" />
                {isHand ? 'Shading guide' : 'View guide'}
                {#if prepState.guideOpened}
                    <span
                        class="rounded bg-sky-100 px-1 py-0.5 text-[9px] font-bold text-sky-800 dark:bg-sky-950/80 dark:text-sky-300"
                        >Instructions opened ✓</span
                    >
                {/if}
            </button>
        {/if}
        {#if state === 'challenge_ready' && onMarkPrinted && !isUploaded}
            <button
                type="button"
                aria-label="Mark printed & continue to capture"
                class="focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200"
                onclick={() =>
                    onMarkPrinted({
                        method,
                        challengeId:
                            data.challenge_id ?? data.challenge?.challenge_id,
                    })}
                disabled={busy}
            >
                <Check class="size-3.5" aria-hidden="true" />Mark printed &amp; continue to capture
            </button>
        {/if}
        {#if canSelectMethod}
            <button
                type="button"
                aria-label={isUploaded
                    ? (isHand ? 'Work on hand-marked supplier test' : 'Work on blank supplier baseline')
                    : `Work on ${isHand ? 'hand-shaded' : 'pre-shaded'} test`}
                class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                onclick={() => onSelectMethod?.(method)}
                disabled={busy}
            >
                Work on this method
            </button>
        {/if}
    </div>

    {#if isAnalyzing || isAnalysisFailure}
        <section
            class="rounded-xl border p-3.5 {isAnalysisFailure
                ? 'border-rose-200 bg-rose-50/70 text-rose-950 dark:border-rose-900/60 dark:bg-rose-950/25 dark:text-rose-100'
                : 'border-blue-200 bg-blue-50/70 text-blue-950 dark:border-blue-900/60 dark:bg-blue-950/25 dark:text-blue-100'}"
            role="status"
            aria-live="polite"
            aria-atomic="false"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h4 class="text-xs font-semibold">
                        {isAnalysisFailure
                            ? 'Capture analysis stopped'
                            : 'Analyzing captured page'}
                    </h4>
                    <p
                        class="mt-0.5 text-[10px] leading-relaxed {isAnalysisFailure
                            ? 'text-rose-800 dark:text-rose-300'
                            : 'text-blue-800 dark:text-blue-300'}"
                    >
                        {activePhaseLabel}
                    </p>
                </div>
            </div>
            {#if isAnalyzing && activePhaseCode && !isActiveCapture}
                <div
                    class="mt-3"
                    data-omr-physical-analysis-progress
                >
                    <div
                        class="mb-1.5 flex items-center justify-between gap-3"
                    >
                        <span class="text-[10px] font-semibold">
                            OMR engine pipeline
                        </span>
                        <span
                            class="shrink-0 font-mono text-xs font-bold tabular-nums"
                            aria-label={`Analysis progress ${analysisProgressPct}%`}
                        >
                            {analysisProgressPct}%
                        </span>
                    </div>
                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-blue-200/70 dark:bg-blue-900/50"
                        role="progressbar"
                        aria-label="Capture analysis progress percentage"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow={analysisProgressPct}
                        aria-valuetext={`${analysisProgressPct}% across the OMR engine pipeline`}
                    >
                        <div
                            class="h-full rounded-full bg-blue-600 transition-[width] duration-500 motion-reduce:transition-none dark:bg-blue-400"
                            style={`width: ${analysisProgressPct}%`}
                        ></div>
                    </div>
                </div>
            {/if}
            {#if isAnalyzing || isAnalysisFailure}
            <ol class="mt-3" aria-label="Capture analysis progress">
                {#each analysisSteps as step, index}
                    <li
                        class="relative flex min-w-0 gap-3 pb-3 last:pb-0"
                        aria-current={['current', 'failed'].includes(step.state)
                            ? 'step'
                            : undefined}
                        aria-label="Step {index +
                            1}, {step.label}: {step.state}"
                    >
                        {#if index < analysisSteps.length - 1}
                            <span
                                class="absolute start-[11.5px] top-6 bottom-0 w-px {step.state ===
                                'complete'
                                    ? isAnalysisFailure
                                        ? 'bg-rose-500 dark:bg-rose-400'
                                        : 'bg-blue-600 dark:bg-blue-400'
                                    : isAnalysisFailure
                                      ? 'bg-rose-200 dark:bg-rose-900'
                                      : 'bg-blue-200 dark:bg-blue-800'}"
                                aria-hidden="true"
                            ></span>
                        {/if}
                        <span
                            class="relative z-10 inline-flex size-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold tabular-nums {step.state ===
                            'complete'
                                ? isAnalysisFailure
                                    ? 'border-rose-500 bg-rose-500 text-white dark:border-rose-400 dark:bg-rose-400 dark:text-rose-950'
                                    : 'border-blue-600 bg-blue-600 text-white dark:border-blue-400 dark:bg-blue-400 dark:text-blue-950'
                                : step.state === 'failed'
                                  ? 'border-rose-700 bg-white text-rose-800 shadow-sm dark:border-rose-300 dark:bg-rose-950 dark:text-rose-200'
                                  : step.state === 'current'
                                    ? 'border-blue-700 bg-white text-blue-800 shadow-sm dark:border-blue-300 dark:bg-blue-950 dark:text-blue-200'
                                    : isAnalysisFailure
                                      ? 'border-rose-300 bg-rose-100/80 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-400'
                                      : 'border-blue-300 bg-blue-100/80 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400'}"
                            aria-hidden="true"
                        >
                            {#if step.state === 'complete'}
                                <Check class="size-3.5" />
                            {:else}
                                {index + 1}
                            {/if}
                        </span>
                        <div
                            class="flex min-w-0 flex-1 items-center justify-between gap-3 pt-0.5"
                        >
                            <span
                                class="min-w-0 text-[11px] leading-5 font-medium {step.state ===
                                'pending'
                                    ? 'text-slate-600 dark:text-slate-400'
                                    : isAnalysisFailure
                                      ? 'text-rose-950 dark:text-rose-100'
                                      : 'text-blue-950 dark:text-blue-100'}"
                            >
                                {step.label}
                            </span>
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 text-[9px] font-semibold tracking-wide uppercase {step.state ===
                                'pending'
                                    ? 'text-slate-500 dark:text-slate-400'
                                    : isAnalysisFailure
                                      ? 'text-rose-700 dark:text-rose-300'
                                      : 'text-blue-700 dark:text-blue-300'}"
                            >
                                {#if step.state === 'current'}
                                    <Loader2
                                        class="size-3 animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                    Current
                                {:else if step.state === 'complete'}
                                    Complete
                                {:else if step.state === 'failed'}
                                    Failed
                                {:else}
                                    Waiting
                                {/if}
                            </span>
                        </div>
                    </li>
                {/each}
            </ol>
            {/if}
            {#if isAnalyzing && currentActivityDetail}
                <p
                    class="mt-3 rounded-md border border-blue-200/70 bg-blue-100/40 px-2.5 py-2 text-[10px] leading-relaxed text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200"
                    aria-label="Current analysis activity"
                >
                    <span class="font-semibold">Current activity:</span>
                    {currentActivityDetail}
                </p>
            {/if}
        </section>
    {/if}

    {#if state === 'failed' && !isNeedsReview}
        <section
            class="rounded-xl border border-rose-300/80 bg-rose-50/70 p-3.5 text-rose-950 dark:border-rose-900/60 dark:bg-rose-950/25 dark:text-rose-100"
            role="alert"
            aria-label="Why verification stopped"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h4 class="text-xs font-semibold">
                        Why verification stopped
                    </h4>
                    <p class="mt-1 text-[11px] font-medium">
                        {failureWhatHappened}
                    </p>
                </div>
                {#if onRetry && !isRunWideFailure}
                    <button
                        type="button"
                        aria-label={`Retake page ${retakePage}`}
                        class="bg-background hover:bg-muted focus-visible:ring-ring inline-flex shrink-0 items-center gap-1 rounded-md border border-rose-300 px-2.5 py-1 text-[10px] font-semibold text-rose-900 focus-visible:ring-2 focus-visible:outline-none dark:text-rose-200"
                        onclick={() =>
                            onRetry?.({ method, pageNo: retakePage })}
                        disabled={busy}
                    >
                        <RefreshCw class="size-3" aria-hidden="true" />
                        Retake page {retakePage}
                    </button>
                {/if}
            </div>

            <div class="mt-3 grid gap-2">
                <div>
                    <p
                        class="text-[9px] font-bold tracking-wide text-rose-700 uppercase dark:text-rose-300"
                    >
                        Engine observation
                    </p>
                    <p class="mt-0.5 text-[10px] leading-relaxed">
                        {failureObservation}
                    </p>
                </div>
                <div>
                    <p
                        class="text-[9px] font-bold tracking-wide text-rose-700 uppercase dark:text-rose-300"
                    >
                        Recommended fix
                    </p>
                    <p class="mt-0.5 text-[10px] leading-relaxed">
                        {failureRecommendedFix}
                    </p>
                </div>
            </div>

            {#if failureTechnicalEntries.length > 0}
                <details
                    class="mt-3 border-t border-rose-200/80 pt-2 dark:border-rose-900/70"
                >
                    <summary
                        class="focus-visible:ring-ring cursor-pointer text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Technical details
                    </summary>
                    <dl class="mt-2 grid gap-1 text-[9px]">
                        {#each failureTechnicalEntries as [label, value]}
                            <div
                                class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] gap-2"
                            >
                                <dt class="font-semibold capitalize">
                                    {label.replaceAll('_', ' ')}
                                </dt>
                                <dd class="min-w-0 font-mono break-words">
                                    {String(value)}
                                </dd>
                            </div>
                        {/each}
                    </dl>
                </details>
            {/if}
        </section>
    {/if}

    {#if isNeedsReview}
        <section
            class="rounded-lg border border-amber-300/80 bg-amber-50/70 p-3 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200"
            role="status"
            aria-live="polite"
        >
            <strong class="text-xs">Review required</strong>
            <p class="mt-1 text-[10px] leading-relaxed text-amber-800 dark:text-amber-300">
                {#if reviewAttempt && canAcceptReviewedAttempt(reviewAttempt)}
                    All flagged items have been reviewed. Accept this reviewed result for this page or retake the page.
                {:else}
                    Some observations need confirmation against this test plan. Review the flagged items and save their decisions to unlock Accept reviewed result, or retake the page.
                {/if}
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                {#if reviewAttempt && onOpenEvidence}
                    <button
                        type="button"
                        class="focus-visible:ring-ring inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-amber-700 px-3 py-2 text-[11px] font-semibold text-white hover:bg-amber-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50 sm:w-auto dark:bg-amber-400 dark:text-amber-950 dark:hover:bg-amber-300"
                        disabled={busy}
                        onclick={() => onOpenEvidence({ method, captureId: reviewAttempt.capture_id, pageNo: reviewAttempt.page_no, filter: evidenceFilterForAttempt(reviewAttempt) })}
                    >
                        <Eye class="size-3.5" aria-hidden="true" />{evidenceButtonLabel(reviewAttempt)}
                    </button>
                {/if}
                {#if reviewAttempt &&
                    canAcceptReviewedAttempt(reviewAttempt) &&
                    (onAcceptReview || onSelectAttempt)}
                    <button
                        type="button"
                        class="focus-visible:ring-ring inline-flex min-h-9 items-center gap-1 rounded-md bg-emerald-700 px-2.5 py-1.5 text-[10px] font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
                        onclick={() =>
                            (onAcceptReview ?? onSelectAttempt)?.({
                                method,
                                captureId: reviewAttempt.capture_id,
                                pageNo: reviewAttempt.page_no,
                            })}
                        disabled={busy}
                    >
                        <Check class="size-3" aria-hidden="true" />Accept reviewed result
                    </button>
                {/if}
                {#if onRetry}
                    <button
                        type="button"
                        class="bg-background text-foreground hover:bg-muted focus-visible:ring-ring inline-flex min-h-9 items-center gap-1 rounded-md border border-amber-300 px-2.5 py-1.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
                        onclick={() => onRetry?.({ method, pageNo: Number(reviewAttempt?.page_no ?? retakePage) })}
                        disabled={busy}
                    >
                        <RefreshCw class="size-3" aria-hidden="true" />Retake page {Number(reviewAttempt?.page_no ?? retakePage)}
                    </button>
                {/if}
            </div>
        </section>
    {/if}

    {#if isCaptureQueued}
        <div
            class="rounded-lg border border-sky-200/80 bg-sky-50/70 p-3 text-[11px] text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/25 dark:text-sky-100"
            role="status"
            aria-live="polite"
        >
            <strong class="text-xs">Capture queued</strong>
            <p
                class="mt-1 text-[10px] leading-relaxed text-sky-800 dark:text-sky-300"
            >
                {captureQueueReason ??
                    'Select this method when you are ready to capture its sheet.'}
            </p>
            {#if onSelectMethod}
                <button
                    type="button"
                    aria-label={`Use ${isHand ? 'hand-shaded' : 'pre-shaded'} capture`}
                    class="bg-background hover:bg-muted focus-visible:ring-ring mt-2 inline-flex items-center gap-1.5 rounded-md border border-sky-300 px-2.5 py-1 text-[10px] font-semibold text-sky-900 focus-visible:ring-2 focus-visible:outline-none dark:border-sky-800 dark:text-sky-200"
                    onclick={() => onSelectMethod?.(method)}
                    disabled={busy}
                >
                    Use this capture
                </button>
            {/if}
        </div>
    {/if}
    {#if canCapture}
        <div class="border-border/70 border-t pt-3">
            <div class="mb-2 flex flex-wrap gap-2" aria-label="Capture pages">
                {#each pageList as page (page.page_no)}
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none {Number(selectedPage) === Number(page.page_no) ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/30' : 'border-border bg-background text-muted-foreground hover:bg-muted'}" aria-pressed={Number(selectedPage) === Number(page.page_no)} onclick={() => { pageExplicitlySelected = true; selectedPage = Number(page.page_no); }}>
                        <span class="size-1.5 rounded-full {page.state === 'awaiting_capture' ? 'bg-sky-500' : page.state === 'needs_review' || page.state === 'failed' ? 'bg-amber-500' : 'bg-muted-foreground/50'}"></span>
                        Page {page.page_no}: {String(page.state ?? '').replace(/_/g, ' ')}
                    </button>
                {/each}
            </div>
            {#if selectedPageNeedsRetake}
                <div
                    class="mb-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-950 dark:border-amber-400/20 dark:bg-amber-950/40 dark:text-amber-200"
                    role="alert"
                >
                    <div class="flex items-start gap-2">
                        <AlertTriangle class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium leading-relaxed">
                                Page {selectedPage} already has analyzed evidence ({selectedPageEntry.state}). Start a retake before uploading another image.
                            </p>
                            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                {#if onRetry && !isNeedsReview && state !== 'failed'}
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-800 active:bg-amber-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50 dark:bg-amber-600 dark:hover:bg-amber-500 dark:text-neutral-950"
                                        onclick={() => onRetry?.({ method, pageNo: selectedPage })}
                                        disabled={busy}
                                    >
                                        <RefreshCw class="size-3" />
                                        Retake Page {selectedPage}
                                    </button>
                                {/if}
                                {#if firstAwaitingPage !== selectedPage && pageList.some((page) => page.state === 'awaiting_capture')}
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-amber-500/30 bg-background/80 px-3 py-1.5 text-xs font-semibold text-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:border-amber-400/30 dark:bg-neutral-900/80 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                        onclick={() => { pageExplicitlySelected = true; selectedPage = firstAwaitingPage; }}
                                    >
                                        Switch to Page {firstAwaitingPage}
                                    </button>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
            {:else}
                <fieldset class="mb-3 rounded-lg border p-2">
                    <div class="flex flex-col gap-2 text-xs sm:flex-row sm:gap-5">
                        <label class="inline-flex cursor-pointer items-center gap-2"><input type="radio" name={`crop-policy-${method}`} value="auto_document_quad" bind:group={cropPolicy} />Autocrop</label>
                        <label class="inline-flex cursor-pointer items-center gap-2"><input type="radio" name={`crop-policy-${method}`} value="keep_original" bind:group={cropPolicy} />Keep original</label>
                    </div>
                </fieldset>
                {#if canResumeCapture}
                    <div class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-sky-200/80 bg-sky-50/60 p-2 text-[10px]">
                        <span><strong>Existing capture</strong> is ready. Resume restores this surface without creating a new attempt.</span>
                        <button type="button" class="rounded-md border px-2.5 py-1 font-semibold" onclick={() => onResumeCapture?.({ method, pageNo: selectedPage })} disabled={busy}><Upload class="size-3" aria-hidden="true" />Resume capture</button>
                    </div>
                {/if}
                <OmrCaptureInput
                    {submit}
                    busy={captureBusy}
                    disabled={captureDisabled || !isPageAwaiting}
                    disabledReason={!isPageAwaiting ? 'This page is not awaiting capture.' : 'Capture opens once this test sheet is printed.'}
                    accept={['image/jpeg', 'image/png', 'image/webp', 'application/pdf']}
                    labels={{ runLabel: `Capture page ${selectedPage}`, busyLabel: 'Analyzing capture…', captureLabel: 'Capture snapshot' }}
                />
            {/if}
        </div>
    {/if}

    {#if pages.length > 0}
        <div
            class="border-border/70 flex flex-col gap-2 border-t pt-2.5"
            aria-label="Selected attempts"
        >
            {#each pages as page (page.page_no)}
                {@const selected = pageAttempt(page)}
                <div
                    class="border-border/70 bg-muted/15 rounded-lg border p-2.5 text-[10px]"
                >
                    <div class="flex items-center justify-between gap-2">
                        <strong>Page {page.page_no}</strong>
                        <span class="text-muted-foreground"
                            >{selected
                                ? 'Selected pass'
                                : 'No selected pass'}</span
                        >
                    </div>
                    {#if selected}
                        <p class="text-muted-foreground mt-1">
                            Attempt {selected.attempt_no ??
                                selected.attempt ??
                                '—'} is the selected passing evidence.
                        </p>
                    {:else}
                        <p class="text-muted-foreground mt-1">
                            Complete and accept a passing attempt for this page.
                        </p>
                    {/if}
                    {#if selected?.capture_id && onOpenEvidence}
                        <button
                            type="button"
                            class="text-primary focus-visible:ring-ring mt-2 inline-flex items-center gap-1 text-[10px] font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                            onclick={() =>
                                onOpenEvidence({
                                    method,
                                    captureId: selected.capture_id,
                                    pageNo: page.page_no,
                                })}
                        >
                            <Eye class="size-3" aria-hidden="true" />Open
                            evidence
                        </button>
                    {/if}
                </div>
            {/each}
        </div>
    {/if}

    {#if firstAcceptancePasses.length > 0}
        <div
            class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-2.5 text-[10px] text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200"
        >
            <p
                class="text-xs font-semibold text-emerald-950 dark:text-emerald-100"
            >
                Passing attempt ready for acceptance
            </p>
            {#each firstAcceptancePasses as attempt (attempt.capture_id)}
                <div
                    class="bg-background/80 mt-2 flex flex-col gap-2 rounded-md border border-emerald-300/80 p-2"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-foreground font-semibold"
                            >{attemptLabel(attempt)}</span
                        >
                        {#if onOpenEvidence}
                            <button
                                type="button"
                                class="text-primary focus-visible:ring-ring inline-flex items-center gap-1 text-[10px] font-semibold hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                onclick={() =>
                                    onOpenEvidence({
                                        method,
                                        captureId: attempt.capture_id,
                                        pageNo: attempt.page_no,
                                    })}
                            >
                                <Eye class="size-3" aria-hidden="true" />Inspect
                                evidence
                            </button>
                        {/if}
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground"
                            >All corner fiducials and alignment gates cleared
                            cleanly.</span
                        >
                        <button
                            type="button"
                            class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md bg-emerald-700 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"
                            disabled={busy}
                            onclick={() =>
                                acceptFirstAttempt(attempt, attempt.page_no)}
                        >
                            <Check class="size-3.5" aria-hidden="true" />Accept
                            result
                        </button>
                    </div>
                </div>
            {/each}
        </div>
    {/if}

    {#if attempts.length > 0 || discardedAttempts.length > 0}
        <details class="border-border/70 border-t pt-2.5">
            <summary class="text-muted-foreground focus-visible:ring-ring cursor-pointer list-none text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Attempt history ({attempts.length})</summary>
            <div class="mt-2 space-y-1.5">
                {#each attempts as attempt (attempt.capture_id)}
                    {@const selected = isAttemptSelected(attempt)}
                    {@const canUseAsReplacement = isReplacementCandidate(attempt)}
                    {@const isReplacing = canUseAsReplacement && sameCapture(replacementAttemptId, attempt.capture_id)}
                    {@const isDiscarding = sameCapture(discardAttemptId, attempt.capture_id)}
                    {@const pendingReview = isReviewPending(attempt) && attemptPageAllowsReview(attempt)}
                    {@const attemptState = attemptLifecycleState(attempt)}
                    <div class="border-border/70 bg-muted/15 rounded-md border px-2.5 py-2 text-[10px]">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-wrap items-center gap-1.5"><strong>{attemptLabel(attempt)}</strong>
                                {#if attempt.is_latest}<span class="rounded bg-muted px-1.5 py-0.5 text-[9px] font-semibold text-foreground/80 border border-border/60" title="Most recent capture for this test method">Latest</span>{/if}
                                {#if attemptState === 'active' || attemptState === 'queued'}
                                    <span>{attemptState === 'queued' ? 'Queued' : 'Analyzing'}</span>
                                {:else if pendingReview}
                                    <span class="rounded border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">Review required</span>
                                {:else if attemptState === 'failed'}
                                    <span class="text-destructive">Failed · {attempt.failure_reason ?? attempt.failure_code ?? attempt.error_code ?? 'unknown_failure'}</span>
                                {:else}
                                    · {attemptState}
                                {/if}</span>
                            <span class="text-muted-foreground">{selected ? 'Selected pass' : isSupersededAttempt(attempt) ? 'Superseded' : ['active', 'queued'].includes(attemptState) ? 'Analysis in progress' : pendingReview ? canAcceptReviewedAttempt(attempt) ? 'Ready to accept reviewed result' : reviewCount(attempt) === 0 && machineReviewCount(attempt) > 0 ? 'Evidence reviewed — retake page to qualify' : 'Awaiting evidence review' : (attempt.failure_reason ?? attempt.failure_code ?? attempt.error_code ?? 'Retained history')}</span>
                        </div>
                        {#if !['active', 'queued'].includes(attemptState) && attempt.vector_match === false}<p class="text-muted-foreground mt-1">{isUploadedSource && method === 'pre_shaded' ? 'Blank baseline mismatch: a mark or unresolved observation was detected.' : 'The observed answer vector did not match this test plan.'}</p>
                        {:else if !pendingReview && !['active', 'queued'].includes(attemptState) && attempt.actual && attemptState === 'failed'}<p class="text-muted-foreground mt-1">{String(attempt.actual).replaceAll('_', ' ')}</p>{/if}
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            {#if onOpenEvidence && attempt.capture_id && !['active', 'queued'].includes(attemptState)}
                                <button
                                    type="button"
                                    class="text-primary focus-visible:ring-ring inline-flex items-center gap-1 text-[10px] font-semibold hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                    disabled={busy}
                                    onclick={() => onOpenEvidence({ method, captureId: attempt.capture_id, pageNo: attempt.page_no, filter: evidenceFilterForAttempt(attempt) })}
                                >
                                    <Eye class="size-3" aria-hidden="true" />Inspect evidence
                                </button>
                            {/if}
                            {#if pendingReview &&
                                canAcceptReviewedAttempt(attempt) &&
                                (onAcceptReview || onSelectAttempt)}
                                <button
                                    type="button"
                                    class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md bg-emerald-700 px-2 py-1 text-[10px] font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
                                    disabled={busy}
                                    onclick={() =>
                                        (onAcceptReview ?? onSelectAttempt)?.({
                                            method,
                                            captureId: attempt.capture_id,
                                            pageNo: attempt.page_no,
                                        })}
                                >
                                    <Check class="size-3" aria-hidden="true" />Select this attempt
                                </button>
                            {/if}
                            {#if canUseAsReplacement}
                                <button type="button" class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border border-emerald-300 px-2 py-1 text-[10px] font-semibold text-emerald-800 hover:bg-emerald-50 focus-visible:ring-2 focus-visible:outline-none dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-950/40" disabled={discardBusy} onclick={() => beginReplacement(attempt)}><Check class="size-3" aria-hidden="true" />Use this pass</button>
                            {/if}
                            {#if attempt.can_discard && onDiscardAttempt}
                                <button type="button" aria-label={`Discard attempt ${attempt.attempt_no}, page ${attempt.page_no}`} class="focus-visible:ring-ring inline-flex min-h-7 items-center gap-1 rounded-md px-2 py-1 text-[10px] font-semibold text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50" disabled={discardBusy || busy} onclick={() => beginDiscard(attempt)}><Trash2 class="size-3" aria-hidden="true" />Discard</button>
                            {/if}
                        </div>
                        {#if isDiscarding}
                            <section aria-label={`Confirm discard attempt ${attempt.attempt_no}`} aria-busy={discardBusy} class="border-border/70 mt-2 space-y-2 border-t pt-2">
                                <p class="font-semibold">Discard attempt {attempt.attempt_no}?</p>
                                <p class="text-muted-foreground">It will leave the working list and cannot be selected. Its evidence will remain in history.</p>
                                {#if discardError}<p role="alert" class="text-destructive">{discardError}</p>{/if}
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="border-border hover:bg-muted focus-visible:ring-ring rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none" disabled={discardBusy} onclick={cancelDiscard}>Cancel</button>
                                    <button type="button" class="focus-visible:ring-ring rounded-md bg-destructive px-2.5 py-1 text-[10px] font-semibold text-destructive-foreground focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50" disabled={discardBusy} onclick={() => confirmDiscard(attempt)}>{discardBusy ? 'Discarding...' : 'Confirm discard'}</button>
                                </div>
                            </section>
                        {/if}
                        {#if isReplacing}
                            <div class="border-border/70 mt-2 space-y-2 border-t pt-2">
                                <label class="text-foreground block font-semibold" for={`selection-reason-${method}-${attempt.capture_id}`}>Replacement reason</label>
                                <input id={`selection-reason-${method}-${attempt.capture_id}`} bind:value={selectionReason} maxlength="500" placeholder="Why should this pass replace the current selection?" class="border-input bg-background text-foreground focus-visible:ring-ring w-full rounded-md border px-2 py-1 text-xs focus-visible:ring-2 focus-visible:outline-none" />
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none" onclick={cancelReplacement}>Cancel</button>
                                    <button type="button" class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md bg-emerald-700 px-2.5 py-1 text-[10px] font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40" disabled={!selectionReason.trim() || busy} onclick={() => replaceAttempt(attempt, attempt.page_no)}><Check class="size-3" aria-hidden="true" />Confirm replacement</button>
                                </div>
                            </div>
                        {/if}
                    </div>
                {/each}
                {#if attempts.length === 0}<p class="text-muted-foreground px-1 py-2 text-[10px]">No retained attempts. Start a retake to capture this page again.</p>{/if}
                {#if discardedAttempts.length > 0}
                    <details class="mt-2 border-t pt-2">
                        <summary class="text-muted-foreground cursor-pointer text-[10px] font-semibold">Discarded ({discardedAttempts.length})</summary>
                        <div class="mt-2 space-y-1.5">
                            {#each discardedAttempts as attempt (attempt.capture_id)}
                                <div class="border-border/70 bg-muted/15 flex flex-wrap items-center justify-between gap-2 rounded-md border px-2.5 py-2 text-[10px]">
                                    <span><strong>{attemptLabel(attempt)}</strong> · {attempt.attempt_state ?? attempt.status ?? 'unknown'} <span class="text-muted-foreground">Discarded</span></span>
                                    {#if onOpenEvidence && attempt.capture_id}<button type="button" class="text-primary focus-visible:ring-ring inline-flex items-center gap-1 text-[10px] font-semibold hover:underline focus-visible:ring-2 focus-visible:outline-none" onclick={() => onOpenEvidence({ method, captureId: attempt.capture_id, pageNo: attempt.page_no })}><Eye class="size-3" aria-hidden="true" />Inspect evidence</button>{/if}
                                </div>
                            {/each}
                        </div>
                    </details>
                {/if}
            </div>
        </details>
    {/if}
</article>
