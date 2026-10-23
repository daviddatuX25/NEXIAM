<script>
    import { onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import {
        Camera,
        FileImage,
        Image as ImageIcon,
        RefreshCw,
        ScanLine,
        Trash2,
        X,
    } from 'lucide-svelte';
    import {
        success as toastSuccess,
        error as toastError,
        info as toastInfo,
    } from '@/lib/toast.js';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
    import ToastManager from '@/Components/kit/ToastManager.svelte';
    import OmrRegionCrop from '@/Components/Omr/Diagnostics/OmrRegionCrop.svelte';
    import OmrWorkbench from '@/Components/Omr/Workbench/OmrWorkbench.svelte';
    import { normalizeItem } from '@/lib/omrDiagnosticEvidence';
    import { buildOmrCaptureTargetKey } from '@/lib/omrCameraAcquisition';
    import OmrPendingCaptureProgress from '@/Components/Omr/Workbench/OmrPendingCaptureProgress.svelte';
    import {
        acceptOmrWorkbenchProjection,
        isOmrWorkbenchProjection,
    } from '@/lib/omrWorkbench.js';
    import { createOmrWorkbenchClient, isOmrWorkbenchClientError, isOmrWorkbenchValidationError } from '@/lib/omrWorkbenchClient.js';
    import { createOmrOperationalPoller } from '@/lib/omrOperationalRun.js';
    import {
        acceptedCaptureReferenceFromProjection,
        cameraAuthorizationForFrame,
        captureAcceptanceFromProjection,
        captureActionsFromProjection,
        captureFramePayload,
        captureInputFromFrame,
    } from '@/lib/omrCaptureWorkbenchAdapter';
    import {
        saveOmrCaptureSessionRecovery,
        clearOmrCaptureSessionRecovery,
        OmrCaptureSessionRecoveryStorageError,
    } from '@/lib/omrCaptureSessionRecovery';
    import {
        createPairedPhoneSessionClient,
        OmrCameraRequestError,
        rejoinPairedPhoneSession,
        revokePairedPhoneSession,
    } from '@/lib/omrCameraSource';

    import {
        assertProductionItemReviewAcknowledged,
        buildOmrConsoleReviewPayload,
        productionItemReviewKey,
        productionItemReviewUrl,
    } from '@/lib/omrConsoleReview';
    let {
        auth = { user: null },
        page_state = 'empty',
        camera_enabled = true,
        provider = { name: 'nexiam.fake', version: '1', synthetic: true },
        material = null,
        applicants = [],
        session = {},
        template = { identity_region: null, items: [] },
        batch = null,
        run_status = null,
        reproduction_authorizations = [],
        review_urls = {},
        workbench_projection = null,
        qr_disabled_issuances = [],
        supplier_stock_allocations = [],
        roster = [],
    } = $props();
    let pairedCamera = null;
    const fileCaptureOperations = new WeakMap();


    function productionWorkbenchUrls(examSessionId, batchId) {
        const prefix = `/scoring/sessions/${encodeURIComponent(String(examSessionId))}/omr/batches/${encodeURIComponent(String(batchId))}/workbench`;

        return {
            show: prefix,
            capture: `${prefix}/capture`,
            pendingCapture: `${prefix}/pending-capture`,
            adoptPendingCapture: `${prefix}/pending-capture/adopt`,
            confirmPendingIdentity: `${prefix}/pending-capture/identity`,
            retry: (sheetId) =>
                `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/retry`,
            review: (sheetId) =>
                `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/review`,
            resolveApplicant: (sheetId) =>
                `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/resolve-applicant`,
            resolveDuplicate: (sheetId) =>
                `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/resolve-duplicate`,
            complete: `${prefix}/complete`,
        };
    }

    function matchingProductionProjection(value, sessionId, batchId) {
        if (
            sessionId < 1 ||
            batchId < 1 ||
            !isOmrWorkbenchProjection(value) ||
            value.execution_kind !== 'production' ||
            value.workbench_id !== `production:${sessionId}:${batchId}`
        ) {
            return null;
        }

        const context = value.context;
        if (
            context.execution_kind !== 'production' ||
            context.session_id !== sessionId ||
            context.run_id !== batchId ||
            context.session?.id !== sessionId ||
            context.run?.id !== batchId
        ) {
            return null;
        }

        return value;
    }

    let screen = $state('camera');
    let mode = $state('auto');
    let settingsOpen = $state(false);
    let activeTab = $state('all');
    let selectedSheetId = $state(null);
    let activeReviewPage = $state(1);
    let fileInput = $state(null);
    let failedImageIds = $state(new Set());
    let fileCaptureTarget = null;
    let replacingSheet = $state(null);
    let replacingPage = $state(null);
    let replacementIntent = null;
    let startingBatch = $state(false);
    let mountIdempotencyKey = newIdempotencyKey();
    let batchStartError = $state(null);
    let savingReview = $state(false);
    let confirmingBatch = $state(false);
    let abstainReason = $state('');
    let exceptionAssignmentId = $state('');
    let exceptionReason = $state('');
    let resolvingApplicant = $state(false);
    let resolvingIssueId = $state(null);
    let resolvingDuplicate = $state(false);
    let duplicateReason = $state('');
    let manualQrState = $state('');
    let manualDisplayCode = $state('');
    let manualIdentityConfirmed = $state(false);
    let reproductionDisplayCode = $state('');
    let reproductionPageNo = $state(1);
    let reproductionQuantity = $state(1);
    let reproductionReason = $state('');
    let reproductionExpiry = $state('');
    let authorizingReproduction = $state(false);
    // Server-driven capture preflight (marker batches run CropOnMarkers; scan
    // batches answer not_applicable and keep the normal upload path).
    const itemReviewParentVersions = new Map();
    let preflightState = $state('idle'); // idle | checking | validated | rejected | error
    let preflight = $state(null); // typed preflight payload
    let preflightError = $state('');
    let preflightTargetKey = null;

    let evidenceRequestGeneration = 0;
    let evidencePage = $state(null);
    let evidenceLoading = $state(false);
    let evidenceError = $state(null);
    let evidenceArea = $state('all');
    let evidenceFilter = $state(null);
    let evidencePageNo = $state(1);
    let triageInventory = $state({ key: '', loading: false, loaded: false, error: null, items: [] });
    let triageInventoryGeneration = 0;
    let evidencePerPage = $state(25);
    let evidenceTab = $state('aligned');
    let evidenceReviewDirty = $state(false);
    let mobileEvidenceExpanded = $state(false);
    let evidenceReviewContext = $state(null);
    let operationalStatus = $state(run_status);
    let runConnection = $state({
        state: batch ? 'connecting' : 'live',
        failures: 0,
        lastSuccessAt: null,
        nextDelayMs: 0,
        message: null,
    });
    const initialProductionProjection = matchingProductionProjection(
        workbench_projection,
        Number(session?.id ?? 0),
        Number(batch?.id ?? 0),
    );
    let productionProjection = $state(initialProductionProjection);
    let productionProjectionLoading = $state(false);
    let productionProjectionError = $state(null);
    let productionProjectionKey = $state(null);
    let productionProjectionRequest = null;
    let productionProjectionRefreshQueued = false;
    let productionWorkbenchClient = $state(null);
    let productionCommandBusy = $state(false);
    let pendingCaptureBusy = $state(false);
    let pendingCaptureError = $state('');
    let selectedQrDisabledIssuanceId = $state('');
    let pendingCaptureStartIdempotencyKey = newIdempotencyKey();
    let pendingRetake = $state(null);

    const evidenceReviewable = $derived(
        evidencePage?.reviewable === true &&
            evidenceReviewContext?.sheetId != null,
    );
    const evidenceBlindReview = $derived(evidencePage?.blind_review === true);
    const evidenceReviewReasons = $derived.by(() => {
        const reasons =
            evidencePage?.review_reasons ?? review_urls?.reasons ?? [];

        return Array.isArray(reasons) ? reasons : [];
    });
    const pageCount = $derived(
        Number(batch?.template?.page_count ?? batch?.expected_page_count ?? 1),
    );

    const qrDisabledMode = $derived(material?.qr_mode === 'qr_disabled');
    const isUploadedStockMode = $derived(
        material?.source_kind === 'uploaded_original',
    );
    const qrDisabledIssuances = $derived(
        isUploadedStockMode
            ? Array.isArray(supplier_stock_allocations)
                ? supplier_stock_allocations
                : []
            : Array.isArray(qr_disabled_issuances)
              ? qr_disabled_issuances
              : [],
    );
    const selectedQrDisabledIssuance = $derived(
        qrDisabledIssuances.find(
            (issuance) => String(issuance.id) === selectedQrDisabledIssuanceId,
        ) ?? null,
    );
    const pendingCapture = $derived(
        qrDisabledMode
            ? (productionProjection?.context?.pending_capture ?? null)
            : null,
    );
    const hasOpenQrDisabledCapture = $derived(
        qrDisabledMode &&
            pendingCapture !== null &&
            pendingCapture.adopted_sheet_id == null,
    );
    const pendingCapturePageNo = $derived(
        pendingRetake?.pageNo ?? pendingCapture?.active_page_no ?? null,
    );
    const workbenchId = $derived(
        batch?.id
            ? `production:${Number(session?.id ?? 0)}:${Number(batch.id)}`
            : '',
    );
    const serverCaptureTarget = $derived(productionProjection?.context?.capture_target ?? null);
    const captureTargetPageNo = $derived(
        Number(qrDisabledMode && pendingRetake ? pendingRetake.pageNo : serverCaptureTarget?.page_no ?? 0),
    );
    const captureTargetClientId = $derived(serverCaptureTarget?.client_capture_id ?? null);
    const captureTargetRevision = $derived(serverCaptureTarget?.target_revision ?? null);
    const captureTargetInstanceId = $derived(serverCaptureTarget?.instance_id ?? null);
    const captureTargetSheetId = $derived(serverCaptureTarget?.sheet_id ?? null);
    const captureTargetKey = $derived(
        buildOmrCaptureTargetKey({
            workbenchId,
            clientCaptureId: captureTargetClientId ?? null,
            pageNo: captureTargetPageNo,
            captureRevision: captureTargetRevision ?? null,
            instanceId: captureTargetInstanceId ?? null,
            sheetId: captureTargetSheetId ?? null,
        }),
    );
    const pendingPageHasCapture = $derived(
        pendingCapture?.pages?.some(
            (page) =>
                Number(page.page_no) === Number(pendingCapturePageNo) &&
                Number(page.capture_count ?? 0) > 0,
        ) ?? false,
    );
    function isCurrentCaptureTarget(target) {
        return Boolean(
            target &&
                typeof target.workbenchId === 'string' &&
                target.workbenchId.length > 0 &&
                typeof target.clientCaptureId === 'string' &&
                target.clientCaptureId.length > 0 &&
                Number.isSafeInteger(Number(target.pageNo)) &&
                Number(target.pageNo) > 0 &&
                target.workbenchId === workbenchId &&
                target.targetKey === captureTargetKey &&
                target.clientCaptureId === captureTargetClientId &&
                Number(target.pageNo) === captureTargetPageNo &&
                String(target.captureRevision ?? '') ===
                    String(captureTargetRevision ?? '') &&
                String(target.instanceId ?? '') ===
                    String(captureTargetInstanceId ?? '') &&
                String(target.sheetId ?? '') ===
                    String(captureTargetSheetId ?? ''),
        );
    }
    function currentCaptureTarget() {
        const target = {
            workbenchId,
            clientCaptureId: captureTargetClientId ?? null,
            pageNo: captureTargetPageNo,
            captureRevision: captureTargetRevision ?? null,
            instanceId: captureTargetInstanceId ?? null,
            sheetId: captureTargetSheetId ?? null,
        };
        return {
            ...target,
            targetKey: buildOmrCaptureTargetKey(target),
        };
    }
    $effect(() => {
        if (preflightTargetKey === captureTargetKey) return;
        preflightTargetKey = captureTargetKey;
        resetPreflight();
    });

    function resetPreflight() {
        preflight = null;
        preflightError = '';
        preflightState = 'idle';
    }

    const sheets = $derived(batch?.sheets ?? []);
    const gallery = $derived(sheets);
    function needsReviewSheet(sheet) {
        return Boolean(sheet?.recovery) || [
            'needs_review',
            'failed',
            'failed_terminal',
        ].includes(String(sheet?.status ?? '').toLowerCase());
    }
    function readySheet(sheet) {
        return !needsReviewSheet(sheet) && [
            'ready',
            'reviewed',
            'processed',
            'complete',
            'completed',
        ].includes(String(sheet?.status ?? '').toLowerCase());
    }
    const needsReviewSheets = $derived(sheets.filter(needsReviewSheet));
    const readySheets = $derived(sheets.filter(readySheet));
    const visibleScans = $derived.by(() => {
        if (activeTab === 'needs_review') return needsReviewSheets;
        if (activeTab === 'ready') return readySheets;
        if (activeTab === 'draft') {
            return sheets.filter((sheet) => sheet.status !== 'committed');
        }
        return sheets;
    });
    const nextNeedsReviewSheet = $derived.by(() => {
        const remaining = needsReviewSheets.filter(
            (sheet) => Number(sheet.id) !== Number(selectedSheetId),
        );

        return remaining[0] ?? needsReviewSheets[0] ?? null;
    });
    const allReviewed = $derived(
        sheets.length > 0 &&
            sheets.every((sheet) => sheet.status === 'reviewed'),
    );
    // Selected sheet identity, re-derived from every refreshed batch so polling
    // replaces the stale object (queued → processing → needs_review|failed).
    const selectedSheet = $derived(
        sheets.find((sheet) => sheet.id === selectedSheetId) ?? null,
    );
    const duplicateSheet = $derived.by(() => {
        const duplicateId = Number(
            selectedSheet?.resolution?.failure?.context?.duplicate_sheet_id ??
                0,
        );

        return duplicateId > 0
            ? (sheets.find((sheet) => Number(sheet.id) === duplicateId) ?? null)
            : null;
    });
    const reviewBusy = $derived(
        ['pending_pages', 'queued', 'processing'].includes(
            selectedSheet?.status,
        ),
    );
    const reviewFailed = $derived(selectedSheet?.status === 'failed_terminal');
    const reviewReady = $derived(
        selectedSheet != null && !reviewBusy && !reviewFailed,
    );
    function canonicalEvidenceFilter(value) {
        if (value === 'mismatches') return 'mismatch';

        return ['all', 'mismatch', 'needs_review', 'reviewed'].includes(value)
            ? value
            : null;
    }

    function persistEvidenceUrlState() {
        if (typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        const values = {
            evidence_sheet:
                selectedSheet?.id ?? evidenceReviewContext?.sheetId ?? null,
            evidence_method:
                evidenceReviewContext?.method ??
                evidencePage?.method ??
                'pre_shaded',
            evidence_area: evidenceArea !== 'all' ? evidenceArea : null,

            evidence_filter: evidenceFilter,
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
    function productionClientFor(sessionId, batchId) {
        if (sessionId < 1 || batchId < 1) return null;

        const workbenchId = `production:${sessionId}:${batchId}`;
        if (productionWorkbenchClient?.workbenchId === workbenchId) {
            return productionWorkbenchClient;
        }

        productionWorkbenchClient = createOmrWorkbenchClient({
            workbenchId,
            executionKind: 'production',
            urls: productionWorkbenchUrls(Number(session?.exam_session_id ?? 0), batchId),
        });

        return productionWorkbenchClient;
    }

    function adoptProductionProjection(value) {
        const sessionId = Number(session?.id ?? 0);
        const batchId = Number(batch?.id ?? 0);
        const next = matchingProductionProjection(value, sessionId, batchId);

        if (next === null) {
            throw new Error(
                'The production OMR workbench returned an invalid route projection.',
            );
        }

        if (
            productionProjection !== null &&
            !acceptOmrWorkbenchProjection(productionProjection, next)
        ) {
            return productionProjection;
        }

        productionProjection = next;
        productionProjectionError = null;

        return next;
    }

    function adoptQrDisabledPendingProjection(value) {
        const sessionId = Number(session?.id ?? 0);
        const batchId = Number(batch?.id ?? 0);
        const candidate =
            value &&
            typeof value === 'object' &&
            !Array.isArray(value) &&
            value.projection &&
            typeof value.projection === 'object'
                ? value.projection
                : value;
        const next = matchingProductionProjection(
            candidate,
            sessionId,
            batchId,
        );

        if (next === null) {
            throw new Error(
                'The production OMR workbench returned an invalid route projection.',
            );
        }

        const current = matchingProductionProjection(
            productionProjection,
            sessionId,
            batchId,
        );
        const currentPending = current?.context?.pending_capture ?? null;
        const nextPending = next.context?.pending_capture ?? null;

        if (current) {
            if (next.revision < current.revision) return current;
            if (
                currentPending?.id &&
                currentPending.id === nextPending?.id &&
                Number(nextPending?.revision ?? 0) <
                    Number(currentPending.revision ?? 0)
            ) {
                return current;
            }
            if (
                next.revision === current.revision &&
                ((!currentPending && !nextPending) ||
                    (currentPending?.id &&
                        currentPending.id === nextPending?.id &&
                        Number(nextPending?.revision ?? 0) <=
                            Number(currentPending.revision ?? 0)) ||
                    (currentPending?.id &&
                        !nextPending &&
                        !currentPending.adopted_sheet_id))
            ) {
                return current;
            }
        }

        productionProjection = next;
        productionProjectionError = null;

        return next;
    }

    async function fetchProductionWorkbenchProjection(sessionId, batchId) {
        const response = await fetch(
            productionWorkbenchUrls(Number(session?.exam_session_id ?? 0), batchId).show,
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            },
        );
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                pendingCaptureErrorMessage(
                    body,
                    'The production workbench status could not be refreshed.',
                ),
            );
        }

        return body;
    }

    async function runProductionCommand(operation, options = {}) {
        const sessionId = Number(session?.id ?? 0);
        const batchId = Number(batch?.id ?? 0);
        const client = productionClientFor(sessionId, batchId);
        const current = matchingProductionProjection(
            productionProjection,
            sessionId,
            batchId,
        );

        if (!client || !current || productionCommandBusy) {
            if (options.propagate === true) {
                throw new Error(
                    'The production OMR workbench is busy or unavailable.',
                );
            }

            return null;
        }

        productionCommandBusy = true;
        productionProjectionError = null;

        try {
            return adoptProductionProjection(await operation(client, current));
        } catch (error) {
            productionProjectionError = {
                detail:
                    error?.message ??
                    'The production OMR workbench command could not be completed.',
            };
            if (options.propagate === true) throw error;

            return null;
        } finally {
            productionCommandBusy = false;
        }
    }
    async function loadProductionProjection() {
        const sessionId = Number(session?.id ?? 0);
        const batchId = Number(batch?.id ?? 0);
        if (sessionId < 1 || batchId < 1) {
            productionProjection = null;
            productionProjectionLoading = false;
            productionProjectionError = null;
            productionProjectionRefreshQueued = false;
            return null;
        }

        if (productionProjectionRequest) {
            productionProjectionRefreshQueued = true;
            return productionProjectionRequest.promise;
        }

        if (productionProjection === null) {
            productionProjectionLoading = true;
        }
        productionProjectionError = null;
        const request = {
            promise: null,
        };
        productionProjectionRequest = request;
        const requestIsCurrent = () =>
            sessionId === Number(session?.id ?? 0) &&
            batchId === Number(batch?.id ?? 0);

        request.promise = (async () => {
            // Install the request state before the fetch can complete.
            await Promise.resolve();

            try {
                const client = productionClientFor(sessionId, batchId);
                if (!client) {
                    throw new Error(
                        'The production OMR workbench is unavailable.',
                    );
                }

                const payload = qrDisabledMode
                    ? await fetchProductionWorkbenchProjection(
                          sessionId,
                          batchId,
                      )
                    : await client.load();
                if (!requestIsCurrent()) return null;

                return qrDisabledMode
                    ? adoptQrDisabledPendingProjection(payload)
                    : adoptProductionProjection(payload);
            } catch (error) {
                if (requestIsCurrent()) {
                    productionProjectionError = {
                        detail:
                            error?.message ??
                            'The production OMR workbench is unavailable.',
                    };
                }

                return null;
            } finally {
                if (productionProjectionRequest !== request) return;

                const refreshQueued = productionProjectionRefreshQueued;
                productionProjectionRefreshQueued = false;
                productionProjectionRequest = null;

                if (refreshQueued) {
                    void loadProductionProjection();
                    return;
                }

                if (requestIsCurrent()) {
                    productionProjectionLoading = false;
                }
            }
        })();

        return request.promise;
    }
    function pendingCaptureErrorMessage(error, fallback) {
        const code = error?.code ?? error?.details?.code ?? null;
        const actionable = {
            omr_production_workbench_stale:
                'The production workbench changed while this page was open. Refresh the pending set before retrying.',
            omr_capture_set_run_stale:
                'The production workbench changed while this page was open. Refresh the pending set before retrying.',
            omr_capture_set_revision_stale:
                'Another page action changed this pending set. Refresh its current page and revision before retrying.',
            omr_capture_set_page_target_mismatch:
                'The server page target changed. Refresh the pending set and capture its current page.',
            qr_disabled_stock_exhausted:
                'This printed issuance has no unclaimed copies. Select another issuance or issue more printed stock.',
            qr_disabled_issuance_invalid:
                'This printed issuance is no longer available. Refresh the page and choose a current issuance.',
        };

        if (typeof error?.message === 'string' && error.message.trim() !== '') {
            return error.message;
        }
        if (typeof error?.detail === 'string' && error.detail.trim() !== '') {
            return error.detail;
        }

        return actionable[code] ?? `${fallback}${code ? ` (${code})` : ''}`;
    }

    async function postPendingCaptureProjection(url, payload, fallback) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                ...csrfHeaders(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(pendingCaptureErrorMessage(body, fallback));
        }

        return adoptQrDisabledPendingProjection(body);
    }


    function reloadQrDisabledBatch() {
        router.reload({
            only: [
                'batch',
                'run_status',
                'qr_disabled_issuances',
                'supplier_stock_allocations',
            ],
            preserveState: true,
            preserveScroll: true,
        });
    }

    async function refreshQrDisabledProductionData() {
        if (pendingCaptureBusy || productionCommandBusy) return;

        pendingCaptureBusy = true;
        pendingCaptureError = '';
        try {
            const refreshed = await loadProductionProjection();
            if (!refreshed) {
                throw new Error(
                    productionProjectionError?.detail ??
                        'The production workbench status could not be refreshed.',
                );
            }
            reloadQrDisabledBatch();
        } catch (error) {
            pendingCaptureError = pendingCaptureErrorMessage(
                error,
                'The pending capture status could not be refreshed.',
            );
            toastError(pendingCaptureError);
        } finally {
            pendingCaptureBusy = false;
        }
    }

    function selectQrDisabledIssuance(event) {
        const nextId = String(event.currentTarget.value ?? '');
        if (nextId === selectedQrDisabledIssuanceId) return;

        selectedQrDisabledIssuanceId = nextId;
        pendingCaptureStartIdempotencyKey = newIdempotencyKey();
        pendingCaptureError = '';
    }

    async function startQrDisabledPendingCapture() {
        const issuance = selectedQrDisabledIssuance;
        if (
            !batch?.id ||
            !issuance ||
            issuance.remaining_claims === 0 ||
            pendingCaptureBusy ||
            productionCommandBusy
        ) {
            return;
        }

        pendingCaptureBusy = true;
        pendingCaptureError = '';
        try {
            await postPendingCaptureProjection(
                productionWorkbenchUrls(session.exam_session_id, batch.id).pendingCapture,
                isUploadedStockMode
                    ? {
                          supplier_stock_allocation_id: Number(issuance.id),
                          idempotency_key: pendingCaptureStartIdempotencyKey,
                      }
                    : {
                          issuance_id: Number(issuance.id),
                          idempotency_key: pendingCaptureStartIdempotencyKey,
                      },
                isUploadedStockMode
                    ? 'The supplier stock allocation could not start a pending capture set.'
                    : 'The printed issuance could not start a pending capture set.',
            );
            pendingCaptureStartIdempotencyKey = newIdempotencyKey();
            pendingRetake = null;
            toastSuccess('Pending capture started at Page 1.');
        } catch (error) {
            pendingCaptureError = pendingCaptureErrorMessage(
                error,
                'The pending capture set could not be started.',
            );
            toastError(pendingCaptureError);
        } finally {
            pendingCaptureBusy = false;
        }
    }

    async function adoptQrDisabledPendingCapture() {
        const pending = pendingCapture;
        if (!pending?.id || pendingCaptureBusy || productionCommandBusy) {
            return;
        }

        pendingCaptureBusy = true;
        pendingCaptureError = '';
        try {
            await postPendingCaptureProjection(
                productionWorkbenchUrls(session.exam_session_id, batch.id)
                    .adoptPendingCapture,
                {
                    pending_capture_id: pending.id,
                    pending_capture_revision: Number(pending.revision),
                },
                'The captured pages could not be adopted into sheet review.',
            );
            pendingRetake = null;
            toastSuccess('Pages adopted. The sheet is ready for review.');
            reloadQrDisabledBatch();
        } catch (error) {
            pendingCaptureError = pendingCaptureErrorMessage(
                error,
                'Adoption is still blocked. Review the recovery guidance and retry.',
            );
            toastError(pendingCaptureError);
        } finally {
            pendingCaptureBusy = false;
        }
    }

    async function confirmPendingCaptureManualIdentity(selections) {
        const pending = pendingCapture;
        if (
            !pending?.id ||
            !Array.isArray(selections) ||
            pendingCaptureBusy ||
            productionCommandBusy
        ) {
            return;
        }

        pendingCaptureBusy = true;
        pendingCaptureError = '';
        try {
            await postPendingCaptureProjection(
                productionWorkbenchUrls(session.exam_session_id, batch.id)
                    .confirmPendingIdentity,
                {
                    pending_capture_id: pending.id,
                    pending_capture_revision: Number(pending.revision),
                    idempotency_key: newIdempotencyKey(),
                    selections,
                },
                'The write-in Applicant Number could not be confirmed.',
            );
            toastSuccess('Write-in Applicant Number confirmed.');
        } catch (error) {
            pendingCaptureError = pendingCaptureErrorMessage(
                error,
                'The write-in Applicant Number could not be confirmed.',
            );
            toastError(pendingCaptureError);
        } finally {
            pendingCaptureBusy = false;
        }
    }

    function canLeavePendingCapture() {
        if (
            !hasOpenQrDisabledCapture &&
            !(qrDisabledMode && pendingCaptureBusy)
        )
            return true;
        toastInfo(
            pendingCaptureBusy && qrDisabledMode && !hasOpenQrDisabledCapture
                ? 'Wait for the pending capture action to finish before leaving this workbench.'
                : 'Finish and continue this pending capture set before leaving the production workbench.',
        );
        return false;
    }

    function canLeaveWorkbench() {
        return canLeavePendingCapture() && canLeaveEvidenceReview();
    }
    function beginPendingPageRetake(pageNo, reason) {
        const page = pendingCapture?.pages?.find(
            (entry) => Number(entry.page_no) === Number(pageNo),
        );
        if (
            !hasOpenQrDisabledCapture ||
            !page ||
            Number(page.capture_count ?? 0) < 1 ||
            !String(reason ?? '').trim()
        ) {
            pendingCaptureError =
                'Choose a captured page and enter a reason before retaking it.';
            return;
        }

        pendingCaptureError = '';
        pendingRetake = {
            pageNo: Number(page.page_no),
            reason: String(reason).trim(),
        };
        screen = 'camera';
        triggerUpload();
    }


    async function fetchEvidencePage(context = {}) {
        const sheet = selectedSheet;
        if (!sheet?.id) {
            evidenceError = {
                detail: 'Select a captured sheet before opening evidence.',
            };
            return null;
        }
        if (context.area !== undefined) evidenceArea = context.area;
        if (context.filter !== undefined)
            evidenceFilter = canonicalEvidenceFilter(context.filter);
        if (context.page !== undefined)
            evidencePageNo = Math.max(1, Number(context.page) || 1);
        if (context.perPage !== undefined)
            evidencePerPage = Math.min(
                50,
                Math.max(1, Number(context.perPage) || 25),
            );
        const generation = ++evidenceRequestGeneration;
        const method =
            context.method ??
            evidenceReviewContext?.method ??
            evidencePage?.method ??
            'pre_shaded';
        evidenceLoading = true;
        evidenceError = null;
        evidencePage = null;
        const params = new URLSearchParams({
            method,
            area: evidenceArea || 'all',
            page: String(evidencePageNo),
            per_page: String(evidencePerPage),
        });
        if (context.captureId)
            params.set('capture_id', String(context.captureId));
        if (evidenceFilter) params.set('filter', evidenceFilter);
        persistEvidenceUrlState();
        try {
            const response = await fetch(
                `/scoring/sessions/${session.exam_session_id}/omr/sheets/${sheet.id}/evidence?${params.toString()}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );
            const payload = await response.json().catch(() => null);
            if (generation !== evidenceRequestGeneration) return null;
            if (!response.ok || !payload || typeof payload !== 'object') {
                const error = new Error(
                    payload?.detail ??
                        'The private evidence page is unavailable.',
                );
                error.status = response.status;
                throw error;
            }
            evidencePage = {
                ...payload,
                lazy_asset_urls: {
                    ...(payload.lazy_asset_urls ?? {}),
                    input:
                        payload.lazy_asset_urls?.input ??
                        (hasImageEvidence(sheet) ? reviewImageUrl : null),
                    aligned:
                        payload.lazy_asset_urls?.aligned ??
                        (hasImageEvidence(sheet) ? reviewImageUrl : null),
                },
            };
            evidenceArea = payload.area ?? evidenceArea;
            evidenceFilter = canonicalEvidenceFilter(
                payload.filter ?? evidenceFilter,
            );
            evidencePageNo = Number(payload.page ?? evidencePageNo);
            evidencePerPage = Number(payload.per_page ?? evidencePerPage);
            persistEvidenceUrlState();

            return evidencePage;
        } catch (error) {
            if (generation === evidenceRequestGeneration) {
                evidencePage = null;
                evidenceError = {
                    status: error?.status ?? null,
                    kind: error?.status === 403 ? 'denied' : 'failed',
                    detail:
                        error?.message ??
                        'The private evidence page is unavailable.',
                };
            }

            return null;
        } finally {
            if (generation === evidenceRequestGeneration)
                evidenceLoading = false;
        }
    }

    async function loadBatchTriageInventory(sheetsToReview) {
        const key = `${batch?.id ?? ''}:${sheetsToReview.map((sheet) => `${sheet.id}:${sheet.status ?? ''}:${sheet.version ?? ''}`).join(',')}`;
        if (triageInventory.key === key && (triageInventory.loading || triageInventory.loaded)) return;
        const generation = ++triageInventoryGeneration;
        triageInventory = { key, loading: true, loaded: false, error: null, items: [] };

        try {
            const sheetItems = await Promise.all(
                sheetsToReview
                    .filter((sheet) => !['failed', 'failed_terminal', 'queued', 'processing', 'pending_pages'].includes(String(sheet.status ?? '').toLowerCase()))
                    .map(async (sheet) => {
                        const items = [];
                        let pageNo = 1;
                        let pageCount = 1;
                        while (pageNo <= pageCount) {
                            const params = new URLSearchParams({
                                method: 'pre_shaded',
                                area: 'all',
                                page: String(pageNo),
                                per_page: '50',
                            });
                            const response = await fetch(
                                `/scoring/sessions/${session.exam_session_id}/omr/sheets/${sheet.id}/evidence?${params.toString()}`,
                                {
                                    headers: { Accept: 'application/json' },
                                    credentials: 'same-origin',
                                },
                            );
                            const payload = await response.json().catch(() => null);
                            if (!response.ok || !payload || typeof payload !== 'object') {
                                const error = new Error(
                                    payload?.detail ??
                                        'Batch evidence inventory could not be loaded.',
                                );
                                error.status = response.status;
                                throw error;
                            }
                            pageCount = Math.max(1, Number(payload.page_count) || 1);
                            const rows = Array.isArray(payload.items)
                                ? payload.items
                                : [];
                            for (const row of rows) {
                                const normalized = normalizeItem(row);
                                if (
                                    payload.reviewable !== true ||
                                    normalized.machine?.state !== 'ambiguous'
                                ) {
                                    continue;
                                }
                                const parentVersion =
                                    normalized.parent_version ??
                                    payload.parent_version;
                                const itemRevision = normalized.item_revision;
                                const evidenceHash =
                                    normalized.machine?.evidence_hash ??
                                    normalized.detected?.evidence_hash ??
                                    payload.evidence_hash;
                                items.push({
                                    ...row,
                                    capture_id: sheet.id,
                                    parent_version: parentVersion,
                                    item_revision: itemRevision,
                                    evidence_hash: evidenceHash,
                                    reviewable: true,
                                    evidence: {
                                        ...row,
                                        capture_id: sheet.id,
                                        parent_version: parentVersion,
                                        item_revision: itemRevision,
                                        evidence_hash: evidenceHash,
                                        reviewable: true,
                                    },
                                    evidence_page: pageNo,
                                    imageUrl:
                                        payload.lazy_asset_urls?.aligned ??
                                        payload.lazy_asset_urls?.input ??
                                        null,
                                    cropRect: row.crop_rect ?? row.region ?? null,
                                    sourceWidth: payload.sheet_map?.width ?? null,
                                    sourceHeight: payload.sheet_map?.height ?? null,
                                });
                            }
                            pageNo += 1;
                        }
                        return items;
                    }),
            );
            if (generation !== triageInventoryGeneration) return;
            triageInventory = {
                key,
                loading: false,
                loaded: true,
                error: null,
                items: sheetItems.flat(),
            };
        } catch (error) {
            if (generation !== triageInventoryGeneration) return;
            triageInventory = {
                key,
                loading: false,
                loaded: true,
                error: {
                    kind: error?.status === 403 ? 'denied' : 'failed',
                    detail:
                        error?.message ??
                        'Batch evidence inventory could not be loaded.',
                },
                items: [],
            };
        }
    }

    $effect(() => {
        const sheetsToReview = needsReviewSheets;
        const inventoryKey = `${batch?.id ?? ''}:${sheetsToReview.map((sheet) => `${sheet.id}:${sheet.status ?? ''}:${sheet.version ?? ''}`).join(',')}`;
        if (inventoryKey !== ':') {
            void loadBatchTriageInventory(sheetsToReview);
        }
    });
    function retryBatchTriageInventory() {
        triageInventory = { key: '', loading: false, loaded: false, error: null, items: [] };
        void loadBatchTriageInventory(needsReviewSheets);
    }

    function canLeaveEvidenceReview() {
        if (!evidenceReviewDirty) return true;
        if (typeof window === 'undefined') return false;

        const discard = window.confirm(
            'This item review has unsaved changes. Discard them and continue?',
        );
        if (discard) evidenceReviewDirty = false;

        return discard;
    }

    async function saveEvidenceDecision(payload, item) {
        if (!evidenceReviewable && item?.reviewable !== true) {
            throw new Error('This grading evidence is not reviewable.');
        }

        const sheetId = Number(
            item?.capture_id ??
                item?.sheet_id ??
                evidenceReviewContext?.sheetId ??
                selectedSheet?.id ??
                0,
        );
        const sessionId = Number(session?.id ?? 0);
        const itemKey = productionItemReviewKey(item);
        const expectedParentVersion =
            itemReviewParentVersions.get(sheetId) ??
            payload?.expected_parent_version ??
            item?.parent_version ??
            item?.version;
        const reviewPayload = buildOmrConsoleReviewPayload(
            { ...payload, expected_parent_version: expectedParentVersion },
            item,
            payload?.idempotency_key ?? newIdempotencyKey(),
        );
        const response = await fetch(
            productionItemReviewUrl(Number(session?.exam_session_id ?? 0), sheetId, itemKey),
            {
                method: 'POST',
                headers: {
                    ...csrfHeaders(),
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(reviewPayload),
            },
        );
        const body = await response.json().catch(() => ({}));
        if (!response.ok || body?.outcome !== 'succeeded') {
            const code = String(body?.code ?? '');
            const detail = code ? ` (${code.replaceAll('_', ' ')})` : '';
            throw new Error(
                `The item review was not saved${detail}. Your selection is still available to retry.`,
            );
        }

        const review = assertProductionItemReviewAcknowledged(
            body,
            reviewPayload,
            sheetId,
            itemKey,
        );
        itemReviewParentVersions.set(sheetId, review.parent_version);
        return { review, receipt: body.receipt };
    }
    async function saveEvidenceDecisionAndRefresh(payload, item) {
        const result = await saveEvidenceDecision(payload, item);
        await Promise.all([
            fetchEvidencePage({
                area: evidenceArea,
                filter: evidenceFilter,
                page: evidencePageNo,
            }),
            loadProductionProjection(),
            router.reload({
                only: ['batch', 'run_status'],
                preserveState: true,
                preserveScroll: true,
            }),
        ]);
        triageInventory = { key: '', loading: false, loaded: false, error: null, items: [] };
        void loadBatchTriageInventory(needsReviewSheets);
        return result;
    }

    function evidenceViewChanged({ area, filter, page } = {}) {
        if (!canLeaveEvidenceReview()) return;

        return fetchEvidencePage({ area, filter, page });
    }

    function selectEvidenceBubbleItem(item) {
        if (!item || !canLeaveEvidenceReview()) return;

        const ownerId = Number(item.capture_id ?? item.sheet_id ?? selectedSheetId);
        const owner = sheets.find((sheet) => Number(sheet.id) === ownerId);
        if (owner && Number(owner.id) !== Number(selectedSheetId)) {
            if (!openReview(owner)) return;
        } else {
            screen = 'review';
        }
        const area = item.area_key ?? item.area_id ?? item.area ?? evidenceArea;
        const page = Number(item.evidence_page ?? evidencePageNo);

        return queueMicrotask(() =>
            fetchEvidencePage({
                area,
                page,
                filter: null,
            }),
        );
    }

    function prepareEvidenceReview(sheet) {
        if (!sheet?.id || !canLeaveEvidenceReview()) return;
        const method = 'pre_shaded';
        const sameSheet =
            Number(evidenceReviewContext?.sheetId) === Number(sheet.id);
        selectedSheetId = sheet.id;
        evidenceReviewContext = { sheetId: sheet.id, method };
        activeReviewPage = Number(activeReviewPage) || 1;

        if (sameSheet && evidencePage) {
            persistEvidenceUrlState();
            return;
        }

        evidenceRequestGeneration += 1;
        evidenceLoading = false;
        evidenceArea = 'all';
        evidenceFilter = null;
        evidencePageNo = 1;
        evidencePerPage = 25;
        evidencePage = null;
        evidenceError = null;
        persistEvidenceUrlState();
        queueMicrotask(() =>
            fetchEvidencePage({ captureId: null, method: 'pre_shaded' }),
        );
    }

    function evidenceRetry() {
        fetchEvidencePage({});
    }

    function nextMissingPage(sheet) {
        const pages = sheet.pages ?? [];
        for (
            let no = 1;
            no <= Number(sheet.expected_page_count ?? 1);
            no += 1
        ) {
            if (
                !pages.some(
                    (page) => Number(page.page_no) === no && page.captured,
                )
            )
                return no;
        }
        return null;
    }

    function missingPageCount(sheet) {
        const capturedPages = new Set(
            (sheet.pages ?? [])
                .filter((page) => page.captured)
                .map((page) => Number(page.page_no)),
        );

        return Math.max(
            0,
            Number(sheet.expected_page_count ?? 1) - capturedPages.size,
        );
    }
    const reviewPages = $derived(
        (selectedSheet?.pages?.length ? selectedSheet.pages : null) ?? null,
    );
    const activeReviewPageData = $derived(
        reviewPages?.find(
            (page) => Number(page.page_no) === activeReviewPage,
        ) ?? null,
    );
    const reviewImageUrl = $derived(
        activeReviewPageData?.image_url ?? selectedSheet?.image_url ?? null,
    );
    function pageLabel(sheet, pageNo) {
        const page = (sheet.pages ?? []).find(
            (candidate) => Number(candidate.page_no) === pageNo,
        );
        return page?.captured ? 'Captured' : 'Missing';
    }

    const captureTemplate = $derived(
        batch?.template ?? template ?? { identity_region: null, items: [] },
    );
    const templateItems = $derived(captureTemplate.items ?? []);
    const scoreRows = $derived(
        Object.entries(
            selectedSheet?.reviewed?.per_area_scores ??
                selectedSheet?.proposal?.per_area_scores ??
                {},
        ).map(([areaId, proposal]) => {
            const area = templateItems.find(
                (item) => String(item.area_id) === String(areaId),
            );

            return {
                areaId,
                areaCode: area?.area_code ?? `Area ${areaId}`,
                areaName: area?.area_name ?? 'Aptitude area',
                proposal,
            };
        }),
    );
    const observedApplicantNumber = $derived(
        selectedSheet?.detected_applicant_number ?? null,
    );

    function observedSetCode(sheet) {
        return sheet?.observed_set_code ?? sheet?.detected_set_code ?? null;
    }
    const evidenceScore = $derived.by(() => {
        const earned = scoreRows.reduce(
            (total, row) => total + Number(row.proposal?.score ?? 0),
            0,
        );
        const possible = scoreRows.reduce(
            (total, row) => total + Number(row.proposal?.max_items ?? 0),
            0,
        );

        return possible > 0
            ? { earned, possible, display: `${earned} / ${possible}` }
            : null;
    });
    const evidenceContextSummary = $derived({
        simulated: false,
        answer_sheet_number: selectedSheet?.answer_sheet_number ??
            (selectedSheet?.id
                ? `Grading.OMR.Sheet.${String(selectedSheet.id).padStart(4, '0')}`
                : null),
        applicant_name: selectedSheet?.applicant_name ??
            (observedApplicantNumber === null
                ? null
                : `Observed No. ${observedApplicantNumber}`),
        examinee_id:
            selectedSheet?.examinee_id ??
            selectedSheet?.detected_applicant_number ??
            null,
        exam_session: session?.exam_code ?? null,
        schedule: null,
        room_seat: session?.room_name ?? null,
        score: evidenceScore,
    });
    const requiredIssues = $derived(
        (selectedSheet?.issues ?? []).filter(
            (issue) =>
                issue.state === 'open' &&
                ['required', 'terminal'].includes(issue.severity),
        ),
    );
    const identityExceptionOpen = $derived.by(() => {
        const issues = selectedSheet?.issues ?? [];
        const hasIdentityIssue = issues.some(
            (issue) =>
                issue.state === 'open' &&
                ['identity_unresolved', 'identity_conflict'].includes(
                    issue.issue_code,
                ),
        );

        return Boolean(
            selectedSheet &&
            (selectedSheet.identity_exception_required === true ||
                selectedSheet.identity_exception === true ||
                selectedSheet.identity_resolution?.requires_exception ===
                    true ||
                hasIdentityIssue),
        );
    });

    const exceptionApplicants = $derived(
        applicants.filter(
            (applicant) =>
                applicant?.exam_assignment_id != null ||
                applicant?.assignment_id != null,
        ),
    );

    function exceptionAssignmentIdFor(applicant) {
        return (
            applicant?.exam_assignment_id ?? applicant?.assignment_id ?? null
        );
    }

    function exceptionApplicantLabel(applicant) {
        const reference =
            applicant?.reference_code ??
            applicant?.reference_number ??
            `Assignment ${exceptionAssignmentIdFor(applicant)}`;

        return applicant?.name ? `${reference} · ${applicant.name}` : reference;
    }

    function getExamineeInfo(sheet) {
        const applicantNumber = sheet?.detected_applicant_number ?? null;
        const observedSet = observedSetCode(sheet);
        const name =
            applicantNumber !== null
                ? 'Applicant Number evidence'
                : sheet?.id
                  ? `Scan #${sheet.id}`
                  : 'Unidentified scan';
        const code =
            applicantNumber !== null
                ? `Applicant No. ${applicantNumber}`
                : 'Applicant Number not detected';
        const initials =
            applicantNumber !== null
                ? String(applicantNumber).slice(-2)
                : sheet?.id
                  ? `#${String(sheet.id).padStart(2, '0').slice(-2)}`
                  : '?';

        return { name, code, initials, observedSet };
    }

    function imageKey(sheet) {
        return String(sheet?.id ?? sheet?.thumbnail_url ?? '');
    }

    function imageIsPlaceholder(sheet) {
        const url = sheet?.thumbnail_url;

        return (
            typeof url === 'string' &&
            (url.startsWith('data:image/svg') || /\.svg(?:[?#]|$)/i.test(url))
        );
    }

    function hasImageEvidence(sheet) {
        return Boolean(
            sheet?.thumbnail_url &&
            !failedImageIds.has(imageKey(sheet)) &&
            !imageIsPlaceholder(sheet),
        );
    }

    function evidenceLabel(sheet) {
        if (!sheet?.thumbnail_url) return 'No image evidence';
        if (imageIsPlaceholder(sheet)) return 'Preview placeholder';
        if (failedImageIds.has(imageKey(sheet))) return 'Image unavailable';
        return 'Captured image';
    }

    function markImageUnavailable(sheet) {
        const key = imageKey(sheet);
        if (!key) return;

        failedImageIds = new Set(failedImageIds).add(key);
    }

    function isRegion(value) {
        return Boolean(
            value &&
            typeof value === 'object' &&
            Number(value.w) > 0 &&
            Number(value.h) > 0,
        );
    }

    const pageIdentityRegions = $derived(
        captureTemplate.identity_regions?.[String(activeReviewPage)] ?? null,
    );
    const identityRegion = $derived(
        isRegion(pageIdentityRegions?.applicant_number)
            ? pageIdentityRegions.applicant_number
            : isRegion(captureTemplate.identity_region)
              ? captureTemplate.identity_region
              : null,
    );
    const nameRegion = $derived(
        isRegion(pageIdentityRegions?.name) ? pageIdentityRegions.name : null,
    );

    function issueLabel(issue) {
        return (
            {
                ambiguous_mark: 'Ambiguous mark',
                unreadable: 'Unreadable item',
                blank_mark: 'Blank mark',
                low_confidence: 'Low confidence',
                identity_unresolved: 'Identity unresolved',
                identity_conflict: 'Identity conflict',
                no_geometry: 'No geometry',
                sheet_misaligned: 'Sheet misaligned',
            }[issue.issue_code] ?? issue.issue_code
        );
    }

    function confidenceLabel(value) {
        return value == null ? 'Unqualified' : `${Math.round(value * 100)}%`;
    }

    function identityConfidenceLabel(sheet) {
        return confidenceLabel(sheet?.identity_confidence);
    }

    $effect(() => {
        const sessionId = Number(session?.id ?? 0);
        const batchId = Number(batch?.id ?? 0);
        const nextKey =
            sessionId > 0 && batchId > 0 ? `${sessionId}:${batchId}` : null;
        const hydratedProjection =
            nextKey === null
                ? null
                : matchingProductionProjection(
                      workbench_projection,
                      sessionId,
                      batchId,
                  );
        const currentProjection = matchingProductionProjection(
            productionProjection,
            sessionId,
            batchId,
        );

        if (nextKey === productionProjectionKey) return;

        productionProjectionKey = nextKey;
        productionWorkbenchClient = null;
        productionCommandBusy = false;
        productionProjectionRefreshQueued = false;
        if (nextKey === null) {
            productionProjection = null;
        } else if (hydratedProjection !== null) {
            productionProjection = hydratedProjection;
        } else if (currentProjection === null) {
            productionProjection = null;
        }
        productionProjectionError = null;
        productionProjectionLoading =
            nextKey !== null && productionProjection === null;

        if (nextKey !== null) {
            void loadProductionProjection();
        }
    });

    function openGallery() {
        if (!canLeavePendingCapture() || !canLeaveEvidenceReview()) return;
        screen = 'gallery';
    }

    function openCamera() {
        if (!canLeaveEvidenceReview()) return;
        screen = 'camera';
        selectedSheetId = null;
    }
    function changeWorkbenchStage(view) {
        if (view === 'camera') {
            screen = 'camera';
            selectedSheetId = null;
        } else if (view === 'queue' || view === 'triage') {
            screen = view;
        } else {
            const sheet = selectedSheet ?? nextNeedsReviewSheet ?? sheets[0] ?? null;
            if (sheet?.id) {
                if (Number(selectedSheetId) === Number(sheet.id)) {
                    prepareEvidenceReview(sheet);
                } else {
                    openReview(sheet);
                }
            }
            screen = 'review';
        }
    }



    function setActiveTab(tab) {
        activeTab = tab;
    }
    function openNextNeedsReview() {
        if (nextNeedsReviewSheet) openReview(nextNeedsReviewSheet);
    }


    function triggerUpload() {
        if (qrDisabledMode) {
            if (!pendingCapture || pendingCapture.adopted_sheet_id) {
                toastInfo(
                    'Select a printed issuance and start its pending capture before uploading an image.',
                );
                return;
            }
            if (pendingCaptureBusy || productionCommandBusy) {
                toastInfo(
                    'Wait for the current pending capture action to finish.',
                );
                return;
            }
            if (!pendingRetake && pendingPageHasCapture) {
                toastInfo(
                    'The server has already captured this page. Use its reasoned Retake action to replace it.',
                );
                return;
            }
        }

        fileCaptureTarget = currentCaptureTarget();
        fileInput?.click();
    }

    function startBatch() {
        if (!material || startingBatch) return;
        startingBatch = true;
        batchStartError = null;
        toastInfo('Starting OMR capture batch…');
        router.post(
            `/scoring/sessions/${session.exam_session_id}/omr/batches`,
            { idempotency_key: mountIdempotencyKey },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    startingBatch = false;
                },
                onError: (errors) => {
                    startingBatch = false;
                    batchStartError =
                        errors?.omr ?? 'Could not start OMR capture batch.';
                    toastError(batchStartError);
                },
            },
        );
    }

    function authorizeReproduction() {
        if (
            authorizingReproduction ||
            !reproductionDisplayCode ||
            !reproductionReason
        )
            return;
        authorizingReproduction = true;
        router.post(
            `/scoring/sessions/${session.exam_session_id}/omr/reproduction-authorizations`,
            {
                display_code: reproductionDisplayCode,
                page_no: Number(reproductionPageNo),
                quantity: Number(reproductionQuantity),
                reason: reproductionReason,
                expires_at: reproductionExpiry || null,
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reproductionReason = '';
                    toastSuccess('Emergency copy authorization recorded.');
                },
                onError: (errors) =>
                    toastError(
                        errors.reproduction ??
                            'The copy authorization could not be recorded.',
                    ),
                onFinish: () => {
                    authorizingReproduction = false;
                },
            },
        );
    }


    // Server-driven preflight gate. Never sends the frame to /sheets when the
    // marker registration is rejected or the check itself errors (fail closed).
    // Scan (CropPage) batches answer not_applicable and continue to the normal
    // upload path — scan validation stays authoritative.
    async function runPreflight(input, target, authorization = null) {
        if (!input || !batch || !isCurrentCaptureTarget(target)) return null;
        const targetSessionId = Number(session.exam_session_id);
        const targetBatchId = Number(batch.id);

        preflight = null;
        preflightError = '';
        preflightState = 'checking';

        const form = new FormData();
        if (input.kind === 'raw') {
            form.append('image', input.file);
        } else {
            form.append('staged_frame_id', input.stagedFrameId);
            form.append('pairing_id', input.pairingId);
            form.append('camera_command_id', input.commandId);
        }

        try {
            const res = await fetch(
                `/scoring/sessions/${targetSessionId}/omr/batches/${targetBatchId}/preflight`,
                {
                    method: 'POST',
                    headers: {
                        ...csrfHeaders(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        ...(authorization ? {
                            'X-Omr-Camera-Role': 'controller',
                            'X-Omr-Camera-Token': authorization.token,
                            'X-Omr-Camera-Generation': String(authorization.generation),
                        } : {}),
                    },
                    credentials: 'same-origin',
                    body: form,
                },
            );
            const body = await res.json().catch(() => ({}));
            if (!isCurrentCaptureTarget(target)) return null;

            if (!res.ok && !body?.mode) {
                // Non-typed validation/HTTP failure — fail closed with a retry path.
                preflightState = 'error';
                preflightError =
                    body?.error ??
                    body?.message ??
                    'The sheet could not be checked before upload.';
                return null;
            }

            preflight = body;
            if (body.mode === 'scan') {
                // CropPage scan batches: proceed with the existing upload path and
                // never show a false Validated state.
                preflightState = 'idle';
                return body;
            }
            if (body.outcome === 'validated' && body.submit_allowed) {
                preflightState = 'validated';
                return body;
            }
            preflightState = 'rejected';
            return body;
        } catch {
            if (!isCurrentCaptureTarget(target)) return null;
            preflightState = 'error';
            preflightError =
                'The check service is unavailable — retake or retry before uploading.';
            return null;
        }
    }

    async function captureFromDeck(frame, operation, crop) {
        const target = operation.target;
        if (!frame || frame.targetKey !== target.targetKey || !isCurrentCaptureTarget(target)) {
            throw new Error('The production capture target changed. Retake before uploading.');
        }
        const authorization = cameraAuthorizationForFrame(frame, pairedCamera);
        const input = captureInputFromFrame(frame);
        const result = await runPreflight(input, target, authorization);
        if (!result || !isCurrentCaptureTarget(target) ||
            (result.mode !== 'scan' && !(result.outcome === 'validated' && result.submit_allowed))) {
            return {
                operationId: operation.operationId,
                targetKey: target.targetKey,
                pageNo: target.pageNo,
                status: 'rejected',
                message: result?.detail ?? preflightError ?? 'The original did not pass capture preflight.',
            };
        }
        const pending = pendingCapture;
        const retake = pendingRetake;
        const replacementReason = serverCaptureTarget?.redo_reason ?? retake?.reason;
        const hasManualIdentity = !qrDisabledMode && manualDisplayCode.trim() !== '';
        if (hasManualIdentity && (!['absent', 'undecodable'].includes(manualQrState) || !manualIdentityConfirmed)) {
            throw new Error('Choose the QR failure state and confirm the Display Code before capture.');
        }
        if (!saveOmrCaptureSessionRecovery(auth?.user?.id ?? null, workbenchId, operation)) {
            throw new OmrCaptureSessionRecoveryStorageError();
        }
        let next;
        try {
            next = await runProductionCommand((client, current) => {
                if (!isCurrentCaptureTarget(target)) throw new Error('The production capture target changed before upload.');
                return client.captureStart(input, {
                    ...captureFramePayload(frame, operation, crop),
                    capture_mode: operation.mode === 'manual' ? 'manual' : 'auto',
                    ...(qrDisabledMode && serverCaptureTarget?.pending_capture_id ? {
                        pending_capture_id: serverCaptureTarget.pending_capture_id,
                        pending_capture_revision: Number(serverCaptureTarget.pending_capture_revision),
                    } : {}),
                    ...(replacementReason ? { replacement_reason: replacementReason } : {}),
                    ...(hasManualIdentity ? {
                        qr_state: manualQrState,
                        display_code: manualDisplayCode.trim(),
                        manual_confirmed: true,
                    } : {}),
                }, current.revision, authorization);
            }, { propagate: true });
        } catch (error) {
            if (isOmrWorkbenchValidationError(error) ||
                isOmrWorkbenchClientError(error) && [401, 419].includes(error.status)) {
                clearOmrCaptureSessionRecovery(auth?.user?.id ?? null, workbenchId, operation);
            }
            throw error;
        }
        const receipt = captureAcceptanceFromProjection(next, operation);
        if (receipt.status !== 'pending' &&
            !clearOmrCaptureSessionRecovery(auth?.user?.id ?? null, workbenchId, operation)) {
            throw new Error('The capture result was received, but its recovery reference could not be cleared. Check the existing outcome before continuing.');
        }
        if (receipt.status === 'accepted') {
            pendingRetake = null;
            manualQrState = '';
            manualDisplayCode = '';
            manualIdentityConfirmed = false;
            router.reload({ only: ['batch', 'run_status'], preserveState: true, preserveScroll: true });
        }
        resetPreflight();
        return receipt;
    }

    async function reconcileCapture(operation) {
        const client = productionClientFor(Number(session?.id ?? 0), Number(batch?.id ?? 0));
        if (!client) throw new Error('Reload the production workbench before checking this capture.');
        const next = await client.reconcileCapture(operation.operationId, operation.idempotencyKey);
        const receipt = captureAcceptanceFromProjection(next, operation);
        adoptProductionProjection(next);
        return receipt;
    }

    async function createPhonePairing() {
        const camera = productionProjection?.context?.camera;
        if (typeof camera?.create_url !== 'string') throw new Error('Phone camera pairing is not available for this batch.');
        const payload = {
            workbench_id: workbenchId,
            ...(captureTargetSheetId ? { capture_sheet_id: Number(captureTargetSheetId) } : {}),
        };
        try {
            pairedCamera = await createPairedPhoneSessionClient(camera.create_url, payload);
        } catch (error) {
            if (!(error instanceof OmrCameraRequestError) || error.status !== 409 ||
                error.code !== 'omr_camera_controller_active' ||
                !Number.isSafeInteger(error.details?.controller_generation) ||
                !window.confirm('Another desktop controls this camera. Take control and disconnect its pending camera commands?')) {
                throw error;
            }
            pairedCamera = await createPairedPhoneSessionClient(camera.create_url, {
                ...payload, takeover: true,
                expected_controller_generation: error.details.controller_generation,
            });
        }
        return pairedCamera;
    }

    async function rejoinPhonePairing(bootstrap) {
        const next = await rejoinPairedPhoneSession(bootstrap,
            captureTargetSheetId ? { capture_sheet_id: Number(captureTargetSheetId) } : {});
        pairedCamera = next;
        return next;
    }

    async function revokePhonePairing(bootstrap) {
        await revokePairedPhoneSession(bootstrap);
        if (pairedCamera?.pairingId === bootstrap.pairingId) pairedCamera = null;
    }

    async function captureAction(action, lastAccepted) {
        if (!lastAccepted) return { supported: false, action, reason: 'no_accepted_capture' };
        const grant = captureActionsFromProjection(productionProjection)[action];
        if (!grant || grant.acceptedTargetKey !== lastAccepted.operation.target.targetKey) {
            return { supported: false, action, reason: 'stale_context' };
        }
        const reason = action === 'redo_last'
            ? window.prompt('Reason for recapturing the last accepted page (the previous image remains in history):')?.trim()
            : null;
        if (action === 'redo_last' && !reason) return { supported: true, action, accepted: false, message: 'Redo cancelled; no page was replaced.' };
        await runProductionCommand((client, current) => client.captureTransition(grant.url, {
            action,
            authorization_id: grant.authorizationId,
            accepted_operation_id: lastAccepted.operation.operationId,
            accepted_target_key: lastAccepted.operation.target.targetKey,
            idempotency_key: grant.authorizationId,
            ...(reason ? { replacement_reason: reason } : {}),
        }, current.revision), { propagate: true });
        return { supported: true, action, accepted: true };
    }

    async function captureUploadedFile(file, target) {
        let frozen = fileCaptureOperations.get(file);
        if (!frozen || frozen.operation.target.targetKey !== target.targetKey) {
            const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
            let width = null;
            let height = null;
            try {
                const bitmap = await createImageBitmap(file);
                width = bitmap.width;
                height = bitmap.height;
                bitmap.close();
            } catch {
                // The authoritative upload path validates formats the browser cannot render.
            }
            frozen = {
                frame: {
                    kind: 'raw', file, targetKey: target.targetKey,
                    inputHash: Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join(''),
                    width, height, orientation: null, sourceGeneration: 1,
                },
                operation: {
                    operationId: crypto.randomUUID(), idempotencyKey: newIdempotencyKey(),
                    target, sourceId: 'file-upload', sourceGeneration: 1,
                    configRevision: 1, trigger: 'shutter', mode,
                },
            };
            fileCaptureOperations.set(file, frozen);
        }
        return captureFromDeck(frozen.frame, frozen.operation, { policy: 'auto_document_quad', quad: null });
    }


    function handleFiles(event) {
        const input = event.currentTarget;
        const file = input?.files?.[0];
        if (!file) return;
        const target = fileCaptureTarget ?? currentCaptureTarget();
        fileCaptureTarget = null;


        if (replacingSheet && replacingPage) {
            const replacementSheet = replacingSheet;
            const replacementPage = replacingPage;
            const intent = replacementIntent;
            if (!intent) {
                toastError('Select the page again and give a replacement reason.');
                return;
            }
            runPreflight({ kind: 'raw', file }, target).then((result) => {
                if (
                    !result ||
                    !isCurrentCaptureTarget(target) ||
                    replacingSheet !== replacementSheet ||
                    replacingPage !== replacementPage
                ) {
                    if (input) input.value = '';
                    return;
                }
                if (result.mode !== 'scan' && result.outcome !== 'validated') {
                    if (input) input.value = '';
                    return;
                }
                toastInfo(
                    `Replacing scan #${replacementSheet.id} page ${replacementPage}…`,
                );
                router.post(
                    `/scoring/sessions/${session.exam_session_id}/omr/sheets/${replacementSheet.id}/pages/${replacementPage}/replace`,
                    { image: file, ...intent },
                    {
                        forceFormData: true,
                        onSuccess: () => {
                            toastSuccess(
                                `Scan #${replacementSheet.id} page ${replacementPage} replaced.`,
                            );
                            replacingSheet = null;
                            replacingPage = null;
                            replacementIntent = null;
                        },
                        onError: (errors) => {
                            toastError(
                                errors.omr ?? 'The page could not be replaced.',
                            );
                        },
                        onFinish: () => {
                            if (input) input.value = '';
                            resetPreflight();
                        },
                    },
                );
            });
            return;
        }

        captureUploadedFile(file, target).catch((error) => toastError(error.message)).finally(() => {
            if (input) input.value = '';
        });
    }

    async function retrySheet(sheet) {
        if (
            !sheet?.id ||
            !canLeavePendingCapture() ||
            !canLeaveEvidenceReview()
        )
            return;

        toastInfo(`Retrying scan #${sheet.id}…`);
        await workbenchRetry({
            reason: 'processing',
            sheet_id: Number(sheet.id),
            page_no: nextMissingPage(sheet) ?? 1,
        });
    }

    function rejectBatch() {
        if (
            !batch ||
            confirmingBatch ||
            !canLeavePendingCapture() ||
            !canLeaveEvidenceReview()
        )
            return;
        if (
            !window.confirm(
                'Reject this OMR batch? Every sheet and its evidence will be discarded.',
            )
        )
            return;
        confirmingBatch = true;
        toastInfo('Rejecting OMR batch…');
        router.post(
            `/scoring/sessions/${session.exam_session_id}/omr/batches/${batch.id}/reject`,
            {},
            {
                onSuccess: () => {
                    confirmingBatch = false;
                    toastSuccess('OMR batch rejected; no scores were written.');
                    router.visit(`/scoring/sessions/${session.exam_session_id}`);
                },
                onError: (errors) => {
                    toastError(
                        errors.omr ?? 'The batch could not be rejected.',
                    );
                },
                onFinish: () => {
                    confirmingBatch = false;
                },
            },
        );
    }

    async function resumeSet(sheet) {
        if (!sheet?.id || !canLeavePendingCapture() || !canLeaveEvidenceReview()) return;
        const client = productionClientFor(Number(session?.id ?? 0), Number(batch?.id ?? 0));
        if (!client) return;
        try {
            const next = await client.loadCaptureTarget(sheet.id);
            const target = next.context?.capture_target;
            if (Number(target?.sheet_id) !== Number(sheet.id) ||
                target.client_capture_id !== sheet.client_capture_id ||
                Number(target.page_no) !== nextMissingPage(sheet)) {
                throw new Error('This scan no longer has an eligible missing page.');
            }
            adoptProductionProjection(next);
            mode = sheet.capture_mode ?? mode;
            screen = 'camera';
            toastInfo(`Resuming scan #${sheet.id} — capture page ${target.page_no} of ${pageCount}.`);
        } catch (error) {
            toastError(error?.message ?? 'The scan target could not be resumed.');
        }
    }

    function triggerReplace(sheet, pageNo) {
        if (!canLeavePendingCapture() || !canLeaveEvidenceReview()) return;
        const source = sheet.pages?.find((page) => Number(page.page_no) === Number(pageNo));
        if (!source?.id || !Number.isSafeInteger(Number(source.current_revision)) || Number(source.current_revision) < 1) {
            toastError('Refresh this scan before selecting its exact current page revision.');
            return;
        }
        const reason = window.prompt(`Reason for replacing scan #${sheet.id}, page ${pageNo} (prior evidence is retained):`)?.trim();
        if (!reason) return;
        replacingSheet = sheet;
        replacingPage = pageNo;
        replacementIntent = {
            expected_source_page_id: Number(source.id),
            expected_revision: Number(source.current_revision),
            replacement_reason: reason,
            idempotency_key: newIdempotencyKey(),
        };
        fileCaptureTarget = currentCaptureTarget();
        fileInput?.click();
    }

    function openReview(sheet) {
        if (
            !sheet?.id ||
            !canLeavePendingCapture() ||
            !canLeaveEvidenceReview()
        ) {
            return false;
        }
        selectedSheetId = sheet.id;
        activeReviewPage = 1;
        abstainReason = '';
        screen = 'review';
        if (
            ![
                'pending_pages',
                'queued',
                'processing',
                'failed',
                'failed_terminal',
            ].includes(sheet.status)
        ) {
            prepareEvidenceReview(sheet);
        } else {
            evidenceRequestGeneration += 1;
            evidenceLoading = false;
            evidencePage = null;
            evidenceError = null;
        }
        return true;
    }
    function selectWorkbenchSheet(projectedSheet) {
        const sheet = sheets.find(
            (candidate) => Number(candidate.id) === Number(projectedSheet?.id),
        );
        return sheet ? openReview(sheet) : false;
    }

    async function workbenchRetry(context = null) {
        if (!canLeavePendingCapture()) return;
        const processingRetry =
            context?.reason === 'processing' ||
            context?.sheet_id != null ||
            context?.sheetId != null;
        if (!processingRetry) {
            evidenceRetry(context);
            return;
        }

        const sheetId = Number(
            context?.sheet_id ?? context?.sheetId ?? selectedSheetId ?? 0,
        );
        const sheet = sheets.find(
            (candidate) => Number(candidate.id) === sheetId,
        );
        if (!sheetId || !sheet) return;

        const pageNo = Number(context?.page_no ?? nextMissingPage(sheet) ?? 1);
        const next = await runProductionCommand((client, current) =>
            client.captureRetry(
                sheetId,
                {
                    sheet_id: sheetId,
                    page_no: pageNo,
                    idempotency_key: newIdempotencyKey(),
                },
                current.revision,
            ),
        );
        if (next) {
            selectedSheetId = sheetId;
            screen = 'gallery';
            toastSuccess(`Scan #${sheetId} re-queued for analysis.`);
        }
    }

    async function handleWorkbenchRecovery(action, recovery) {
        if (!canLeavePendingCapture()) return;
        const supportCode =
            typeof recovery?.support_code === 'string'
                ? recovery.support_code
                : '';
        const supportMatch = /^OMR-(\d+)-/.exec(supportCode);
        const recoverySheetId =
            recovery?.capture_id ??
            recovery?.sheet_id ??
            supportMatch?.[1] ??
            selectedSheetId;
        const sheet = sheets.find(
            (candidate) => Number(candidate.id) === Number(recoverySheetId),
        );
        if (sheet) {
            selectedSheetId = sheet.id;
            if (action === 'retry_scan' || action === 'capture_retry') {
                await workbenchRetry({
                    reason: 'processing',
                    sheet_id: sheet.id,
                });
                return;
            }
            if (
                action === 'match_applicant' ||
                action === 'resolve_applicant'
            ) {
                if (
                    exceptionAssignmentId &&
                    exceptionReason.trim().length >= 8
                ) {
                    await resolveApplicantException();
                } else {
                    openReview(sheet);
                }
                return;
            }
            if (
                action === 'compare_duplicates' ||
                action === 'resolve_duplicate'
            ) {
                openReview(sheet);
                return;
            }
            if (action === 'enter_display_code') {
                screen = 'camera';
                settingsOpen = true;
                return;
            }
        }

        const destinationUrl =
            recovery?.destination_url ?? sheet?.recovery?.destination_url;
        if (destinationUrl) {
            openRecoveryDestination({
                ...recovery,
                destination_url: destinationUrl,
            });
        }
    }
    async function completeWorkbench(command) {
        if (command !== 'production_complete') return;

        if (!canLeavePendingCapture() || !canLeaveEvidenceReview()) return;

        const next = await runProductionCommand((client, current) =>
            client.productionComplete(current.revision),
        );
        if (next) {
            screen = 'gallery';
            toastSuccess('OMR scores committed to the grading session!');
        }
    }

    function changeReviewPage(pageNo) {
        if (!canLeaveEvidenceReview()) return;
        activeReviewPage = Number(pageNo) || 1;
    }

    // If the reviewed sheet disappears (batch rejected/regenerated), return to
    // the gallery instead of rendering a dead review.
    $effect(() => {
        if (hasOpenQrDisabledCapture && screen !== 'camera') {
            screen = 'camera';
            return;
        }
        if (screen === 'review' && selectedSheetId != null && !selectedSheet) {
            if (!canLeaveEvidenceReview()) return;
            screen = 'gallery';
        }
    });

    async function saveReview(action = 'confirm') {
        if (
            !selectedSheet ||
            savingReview ||
            reviewBusy ||
            !canLeaveEvidenceReview()
        )
            return;
        if (action === 'abstain' && !abstainReason.trim()) {
            toastError(
                'Enter a reason before marking this sheet for manual follow-up.',
            );
            return;
        }

        savingReview = true;
        toastInfo('Saving sheet review…');
        try {
            const next = await runProductionCommand(
                (client, current) =>
                    client.reviewSave(
                        Number(selectedSheet.id),
                        {
                            action,
                            ...(action === 'abstain'
                                ? { abstain_reason: abstainReason.trim() }
                                : {}),
                            idempotency_key: newIdempotencyKey(),
                        },
                        current.revision,
                    ),
                { propagate: true },
            );
            if (next) {
                screen = 'gallery';
                if (action === 'abstain') {
                    toastInfo('Scan marked for manual follow-up.');
                } else {
                    toastSuccess(
                        'Review saved; confirm the batch to write scores.',
                    );
                }
            }
        } catch (error) {
            toastError(error?.message ?? 'The review could not be saved.');
        } finally {
            savingReview = false;
        }
    }

    async function resolveApplicantException() {
        if (
            !selectedSheet ||
            resolvingApplicant ||
            !exceptionAssignmentId ||
            exceptionReason.trim().length < 8
        ) {
            toastError(
                'Select an eligible applicant and enter a specific resolution reason.',
            );
            return;
        }

        resolvingApplicant = true;
        try {
            const sheetId = Number(selectedSheet.id);
            const next = await runProductionCommand(
                (client, current) =>
                    client.resolveApplicant(
                        sheetId,
                        {
                            exam_assignment_id: Number(exceptionAssignmentId),
                            reason: exceptionReason.trim(),
                            idempotency_key: newIdempotencyKey(),
                        },
                        current.revision,
                    ),
                { propagate: true },
            );
            if (next) {
                exceptionAssignmentId = '';
                exceptionReason = '';
                toastSuccess('Applicant exception resolved and audited.');
            }
        } catch (error) {
            toastError(
                error?.message ??
                    'The applicant exception could not be resolved.',
            );
        } finally {
            resolvingApplicant = false;
        }
    }

    async function resolveDuplicate(survivor, rejected) {
        if (
            !survivor ||
            !rejected ||
            resolvingDuplicate ||
            duplicateReason.trim().length < 8
        ) {
            toastError(
                'Enter a specific reason before choosing the retained scan.',
            );
            return;
        }

        resolvingDuplicate = true;
        try {
            const next = await runProductionCommand(
                (client, current) =>
                    client.resolveDuplicate(
                        Number(survivor.id),
                        {
                            rejected_sheet_id: Number(rejected.id),
                            survivor_version: Number(survivor.version),
                            rejected_version: Number(rejected.version),
                            reason: duplicateReason.trim(),
                            idempotency_key: newIdempotencyKey(),
                        },
                        current.revision,
                    ),
                { propagate: true },
            );
            if (next) {
                duplicateReason = '';
                toastSuccess(
                    `Scan #${survivor.id} retained; duplicate rejected.`,
                );
            }
        } catch (error) {
            toastError(
                error?.message ??
                    'The duplicate disposition could not be saved.',
            );
        } finally {
            resolvingDuplicate = false;
        }
    }

    function openRecoveryDestination(recovery) {
        if (
            !canLeavePendingCapture() ||
            !recovery?.destination_url ||
            typeof window === 'undefined'
        )
            return;

        if (recovery.primary_action === 'open_monitoring' && selectedSheet) {
            const returnTo = `${window.location.pathname}?omr_sheet=${selectedSheet.id}`;
            const separator = recovery.destination_url.includes('?')
                ? '&'
                : '?';
            window.location.assign(
                `${recovery.destination_url}${separator}return_to=${encodeURIComponent(returnTo)}`,
            );
            return;
        }

        window.location.assign(recovery.destination_url);
    }

    function resolveIssue(issue, resolutionCode = 'manual_review') {
        if (!selectedSheet || resolvingIssueId === issue.id) return;
        const reason = abstainReason.trim();
        if (!reason) {
            toastError(
                'Enter a manual follow-up reason before resolving an issue.',
            );
            return;
        }

        resolvingIssueId = issue.id;
        router.post(
            `/scoring/sessions/${session.exam_session_id}/omr/sheets/${selectedSheet.id}/issues/${issue.id}/resolve`,
            {
                resolution_code: resolutionCode,
                resolution_reason: reason,
            },
            {
                onSuccess: () => toastSuccess(`${issueLabel(issue)} resolved.`),
                onError: (errors) =>
                    toastError(
                        errors.omr ?? 'The issue could not be resolved.',
                    ),
                onFinish: () => {
                    resolvingIssueId = null;
                },
            },
        );
    }

    function backToSession() {
        if (!canLeavePendingCapture() || !canLeaveEvidenceReview()) return;
        router.visit(`/scoring/sessions/${session.exam_session_id}`);
    }
    function statusLabel(status) {
        return (
            {
                received: 'Draft',
                queued: 'Queued',
                processing: 'Processing',
                needs_review: 'Needs review',
                reviewed: 'Reviewed',
                committed: 'Committed',
                failed_terminal: 'Failed',
            }[status] ?? status
        );
    }

    function applyOperationalStatus(snapshot) {
        if (!batch || Number(snapshot.run_id) !== Number(batch.id)) return;

        const updates = new Map(
            (snapshot.sheets ?? []).map((sheet) => [Number(sheet.id), sheet]),
        );
        let requiresHydration = false;
        const nextSheets = (batch.sheets ?? []).map((sheet) => {
            const update = updates.get(Number(sheet.id));
            if (!update) return sheet;
            if (
                ['pending_pages', 'queued', 'processing'].includes(
                    sheet.status,
                ) &&
                !['pending_pages', 'queued', 'processing'].includes(
                    update.status,
                )
            ) {
                requiresHydration = true;
            }
            const pages = (sheet.pages ?? []).map((page) => {
                const pageUpdate = (update.pages ?? []).find(
                    (candidate) =>
                        Number(candidate.page_no) === Number(page.page_no),
                );
                return pageUpdate ? { ...page, ...pageUpdate } : page;
            });

            return {
                ...sheet,
                ...update,
                recovery:
                    update.recovery == null
                        ? null
                        : { ...(sheet.recovery ?? {}), ...update.recovery },
                pages,
            };
        });

        batch = {
            ...batch,
            status: snapshot.raw_status,
            sheets: nextSheets,
        };
        operationalStatus = snapshot;
        void loadProductionProjection();

        if (requiresHydration) {
            router.reload({
                only: ['batch', 'run_status'],
                preserveState: true,
                preserveScroll: true,
            });
        }
    }

    function runConnectionLabel() {
        if (runConnection.state === 'live') return 'Live';
        if (runConnection.state === 'connecting') return 'Connecting';
        if (runConnection.state === 'stale') return 'Status stale';

        return 'Reconnecting';
    }

    onMount(() => {
        if (!batch && material && !startingBatch) {
            startBatch();
        }
        const params = new URLSearchParams(window.location.search);
        const resumedSheetId = Number(params.get('omr_sheet') ?? 0);
        const restoredSheetId = Number(params.get('evidence_sheet') ?? 0);
        const restoredMethod = params.get('evidence_method') || 'pre_shaded';
        const restoredArea = params.get('evidence_area') || 'all';
        const restoredFilter = canonicalEvidenceFilter(
            params.get('evidence_filter'),
        );
        const restoredPage = Number(params.get('evidence_page') ?? 1);
        const restoredPerPage = Number(params.get('evidence_per_page') ?? 25);
        const restoredTab = params.get('evidence_tab');
        if (
            !hasOpenQrDisabledCapture &&
            resumedSheetId > 0 &&
            sheets.some((sheet) => Number(sheet.id) === resumedSheetId)
        ) {
            selectedSheetId = resumedSheetId;
            screen = 'review';
            router.reload({
                only: ['batch'],
                preserveState: true,
                preserveScroll: true,
            });
        } else if (restoredSheetId > 0) {
            selectedSheetId = restoredSheetId;
            evidenceReviewContext = {
                sheetId: restoredSheetId,
                method: restoredMethod,
            };
            evidenceArea = restoredArea;
            evidenceFilter = restoredFilter;
            evidencePageNo = Math.max(1, restoredPage || 1);
            evidencePerPage = Math.min(50, Math.max(1, restoredPerPage || 25));
            evidenceTab = ['captured', 'aligned', 'recognition'].includes(
                restoredTab,
            )
                ? restoredTab
                : 'aligned';
            queueMicrotask(() =>
                fetchEvidencePage({
                    method: restoredMethod,
                    captureId: restoredSheetId,
                }),
            );
        }

        const statusPoller = createOmrOperationalPoller({
            statusUrl: batch
                ? `/scoring/sessions/${session.exam_session_id}/omr/batches/${batch.id}/status`
                : null,
            expectedSessionId: Number(session.id ?? 0),
            expectedRunId: Number(batch?.id ?? 0),
            expectedMode: 'production',
            initialRevision: Number(operationalStatus?.revision ?? -1),
            onStatus: applyOperationalStatus,
            onConnection: (connection) => {
                runConnection = connection;
            },
        });
        statusPoller.start();
        const beforeUnload = (event) => {
            if (
                !evidenceReviewDirty &&
                !hasOpenQrDisabledCapture &&
                !(qrDisabledMode && pendingCaptureBusy)
            )
                return;
            event.preventDefault();
            event.returnValue = '';
        };
        const handleKeydown = (event) => {
            if (event.code !== 'Escape') return;
            if (screen === 'review') {
                if (canLeaveEvidenceReview()) {
                    openCamera();
                }
            } else if (screen === 'gallery') {
                openCamera();
            }
        };

        window.addEventListener('keydown', handleKeydown);
        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            statusPoller.stop();
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('keydown', handleKeydown);
        };

    });

</script>

<svelte:head>
    <title>OMR capture · {session.exam_code ?? 'Session'}</title>
</svelte:head>

<div class="omr-app">
    <ToastManager />


    {#if !batch}
        {#if batchStartError}
            <section
                class="start-screen"
                aria-label="Error initializing OMR capture"
            >
                <div class="start-card">
                    <ScanLine size={28} class="text-rose-500" />
                    <h1>Batch initialization failed</h1>
                    <p class="error-msg">{batchStartError}</p>
                    <button
                        class="pair-button"
                        type="button"
                        onclick={startBatch}
                        disabled={startingBatch}
                    >
                        <span
                            >{startingBatch
                                ? 'Retrying…'
                                : 'Retry opening batch'}</span
                        >
                    </button>
                </div>
            </section>
        {:else if material}
            <section class="start-screen" aria-label="Initializing OMR capture">
                <div class="start-card">
                    <RefreshCw size={28} class="spin text-primary" />
                    <h1>Initializing scanner…</h1>
                    <p>
                        Opening batch for <strong
                            >{material.booklet_label}</strong
                        >.
                    </p>
                </div>
            </section>
        {:else}
            <section
                class="start-screen"
                aria-label="No published OMR material"
            >
                <div class="start-card">
                    <ScanLine size={28} />
                    <div class="empty-gallery">
                        <strong>No published OMR material</strong>
                        <span
                            >Return to the Exam Session and publish a finalized
                            Booklet before capture begins.</span
                        >
                    </div>
                </div>
            </section>
        {/if}
    {:else}
        <input
            class="file-input"
            bind:this={fileInput}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onchange={handleFiles}
        />
        {#if productionProjection}
            <OmrWorkbench
                projection={productionProjection}
                connectionState={runConnection.state}
                {evidencePage}
                {evidenceContextSummary}
                {evidenceLoading}
                {evidenceError}
                {triageInventory}
                stageView={screen === 'queue'
                    ? 'queue'
                    : screen === 'triage'
                      ? 'triage'
                      : screen === 'review'
                        ? 'review'
                        : 'live'}
                {selectedSheetId}
                onSelectSheet={selectWorkbenchSheet}
                onRetry={workbenchRetry}
                onRetryTriageInventory={retryBatchTriageInventory}
                onReviewAction={saveEvidenceDecisionAndRefresh}
                onViewChange={evidenceViewChanged}
                onSelectBubbleItem={selectEvidenceBubbleItem}
                onRecoveryAction={handleWorkbenchRecovery}
                onComplete={completeWorkbench}
                onDirtyChange={(dirty) => (evidenceReviewDirty = dirty)}
                onDirtyNavigation={canLeaveWorkbench}
                onBack={() => backToSession()}
                onStageViewChange={changeWorkbenchStage}
                sessionTitle={[session.exam_code, session.room_name]
                    .filter(Boolean)
                    .join(' · ') || 'Session'}
                cameraDeckProps={{
                    workbenchId,
                    clientCaptureId: captureTargetClientId,
                    pageNo: captureTargetPageNo,
                    captureRevision: captureTargetRevision,
                    instanceId: captureTargetInstanceId,
                    sheetId: captureTargetSheetId,
                    authorizedReplacementOfCaptureId: serverCaptureTarget?.replaces_capture_id ?? null,
                    currentPage: captureTargetPageNo || 1,
                    pageCount,
                    registrationMode: material?.registration_mode ?? 'fiducial_anchored',
                    cameraEnabled: camera_enabled,
                    paused: screen !== 'camera' || mobileEvidenceExpanded,
                    disabled:
                        !batch ||
                        !productionProjection?.commands?.includes('capture_start') ||
                        !captureTargetClientId ||
                        captureTargetPageNo < 1 ||
                        captureTargetRevision == null ||
                        (qrDisabledMode &&
                            (!pendingCapture ||
                                pendingCapture.adopted_sheet_id ||
                                pendingCaptureBusy ||
                                productionCommandBusy ||
                                (!pendingRetake && pendingPageHasCapture))),
                    disabledReason: qrDisabledMode
                        ? 'Start or refresh a server-targeted pending capture before scanning.'
                        : 'The production capture target is initializing.',
                    busy: pendingCaptureBusy || productionCommandBusy,
                    cameraCaption: qrDisabledMode
                        ? 'The pending set selects each page; recognition stays Unqualified.'
                        : 'Preflight checks the frame before upload; recognition stays Unqualified.',
                    preflightState,
                    preflight,
                    preflightError,
                    onResetPreflight: resetPreflight,
                    workbenchId,
                    actorId: auth?.user?.id ?? null,
                    onCapture: captureFromDeck,
                    onReconcile: reconcileCapture,
                    onCaptureAction: captureAction,
                    authorizedActions: captureActionsFromProjection(productionProjection),
                    initialAccepted: acceptedCaptureReferenceFromProjection(productionProjection),
                    cameraPairing: productionProjection?.context?.camera?.pairing_available === false
                        ? undefined
                        : { create: createPhonePairing, rejoin: rejoinPhonePairing, revoke: revokePhonePairing },
                    pairingUnavailableReason: productionProjection?.context?.camera?.pairing_unavailable_reason ?? null,
                    allowUpload: !qrDisabledMode || Boolean(pendingCapture),
                    singleUpload: qrDisabledMode,
                }}
                bind:cameraSettingsOpen={settingsOpen}
                bind:cameraMode={mode}
            >
                {#snippet captureContent()}
                    {#if qrDisabledMode}
                        <section
                            class="border-border bg-card mb-4 min-w-0 space-y-3 rounded-xl border p-4 shadow-xs sm:p-5"
                            aria-label="QR-disabled printed issuance capture"
                            data-omr-qr-disabled-pending-capture
                        >
                            <div>
                                <p
                                    class="text-primary text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Printed QR-disabled paper
                                </p>
                                <h2
                                    class="mt-1 text-lg font-semibold tracking-tight"
                                >
                                    Capture a printed issuance
                                </h2>
                                <p
                                    class="text-muted-foreground mt-1 max-w-3xl text-sm leading-relaxed"
                                >
                                    Start the route-bound pending set before
                                    uploading. The server selects each page
                                    target; pages are paired before sheet review
                                    and no partial score is created.
                                </p>
                            </div>

                            {#if pendingCapture}
                                <OmrPendingCaptureProgress
                                    pending={pendingCapture}
                                    production={true}
                                    roster={roster.length ? roster : applicants}
                                    busy={pendingCaptureBusy ||
                                        productionCommandBusy}
                                    onRetake={beginPendingPageRetake}
                                    onAdopt={adoptQrDisabledPendingCapture}
                                    onConfirmIdentity={confirmPendingCaptureManualIdentity}
                                />
                                {#if pendingCapture.adopted_sheet_id}
                                    <button
                                        type="button"
                                        class="bg-primary text-primary-foreground rounded-md px-3 py-2 text-sm font-semibold"
                                        onclick={openGallery}
                                    >
                                        View adopted sheet in capture gallery
                                    </button>
                                    <div
                                        class="border-border bg-background/70 grid min-w-0 gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-4"
                                    >
                                        <label
                                            class="min-w-0 text-sm font-medium"
                                            for="qr-disabled-next-issuance"
                                        >
                                            Next printed issuance
                                            <select
                                                id="qr-disabled-next-issuance"
                                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2"
                                                value={selectedQrDisabledIssuanceId}
                                                onchange={selectQrDisabledIssuance}
                                                disabled={pendingCaptureBusy ||
                                                    productionCommandBusy ||
                                                    qrDisabledIssuances.length ===
                                                        0}
                                            >
                                                <option value=""
                                                    >Choose an authorized
                                                    printed issuance</option
                                                >
                                                {#each qrDisabledIssuances as issuance (issuance.id)}
                                                    <option
                                                        value={String(
                                                            issuance.id,
                                                        )}
                                                        disabled={issuance.remaining_claims ===
                                                            0}
                                                    >
                                                        {issuance.issuance_code} ·
                                                        {issuance.page_count} pages
                                                        · {issuance.remaining_claims ===
                                                        null
                                                            ? 'reusable master'
                                                            : `${issuance.remaining_claims} copies remaining`}
                                                    </option>
                                                {/each}
                                            </select>
                                        </label>
                                        <button
                                            type="button"
                                            class="bg-primary text-primary-foreground rounded-md px-4 py-2.5 text-sm font-semibold disabled:opacity-50"
                                            onclick={startQrDisabledPendingCapture}
                                            disabled={pendingCaptureBusy ||
                                                productionCommandBusy ||
                                                !selectedQrDisabledIssuance ||
                                                selectedQrDisabledIssuance.remaining_claims ===
                                                    0}
                                        >
                                            Start another pending capture
                                        </button>
                                    </div>
                                {/if}
                            {:else}
                                <div
                                    class="border-border bg-background/70 grid min-w-0 gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-4"
                                >
                                    <label
                                        class="min-w-0 text-sm font-medium"
                                        for="qr-disabled-issuance"
                                    >
                                        Printed issuance
                                        <select
                                            id="qr-disabled-issuance"
                                            class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2"
                                            value={selectedQrDisabledIssuanceId}
                                            onchange={selectQrDisabledIssuance}
                                            disabled={pendingCaptureBusy ||
                                                productionCommandBusy ||
                                                qrDisabledIssuances.length ===
                                                    0}
                                        >
                                            <option value=""
                                                >Choose an authorized printed
                                                issuance</option
                                            >
                                            {#each qrDisabledIssuances as issuance (issuance.id)}
                                                <option
                                                    value={String(issuance.id)}
                                                    disabled={issuance.remaining_claims ===
                                                        0}
                                                >
                                                    {issuance.issuance_code} · {issuance.page_count}
                                                    pages · {issuance.remaining_claims ===
                                                    null
                                                        ? 'reusable master'
                                                        : `${issuance.remaining_claims} copies remaining`}
                                                </option>
                                            {/each}
                                        </select>
                                    </label>
                                    <button
                                        type="button"
                                        class="bg-primary text-primary-foreground rounded-md px-4 py-2.5 text-sm font-semibold disabled:opacity-50"
                                        onclick={startQrDisabledPendingCapture}
                                        disabled={pendingCaptureBusy ||
                                            productionCommandBusy ||
                                            !selectedQrDisabledIssuance ||
                                            selectedQrDisabledIssuance.remaining_claims ===
                                                0}
                                    >
                                        {pendingCaptureBusy
                                            ? 'Starting…'
                                            : 'Start pending Page 1'}
                                    </button>
                                    {#if qrDisabledIssuances.length === 0}
                                        <p
                                            class="text-muted-foreground text-sm sm:col-span-2"
                                            role="status"
                                        >
                                            No printed QR-disabled issuance is
                                            available for this session’s
                                            material. Issue and print copies
                                            before capture.
                                        </p>
                                    {:else if selectedQrDisabledIssuance}
                                        <p
                                            class="text-muted-foreground text-xs sm:col-span-2"
                                        >
                                            {selectedQrDisabledIssuance.issuance_code}
                                            · {selectedQrDisabledIssuance.page_count}
                                            pages · {selectedQrDisabledIssuance.copy_policy.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                            {#if selectedQrDisabledIssuance.remaining_claims !== null}
                                                · {selectedQrDisabledIssuance.remaining_claims}
                                                of {selectedQrDisabledIssuance.intended_copy_count}
                                                copies remain
                                            {/if}
                                        </p>
                                    {/if}
                                </div>
                            {/if}

                            {#if pendingCaptureError}
                                <div
                                    class="flex flex-col gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2.5 text-sm text-rose-900 sm:flex-row sm:items-center sm:justify-between dark:text-rose-200"
                                    role="alert"
                                >
                                    <p>{pendingCaptureError}</p>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md border border-current px-3 py-1.5 text-xs font-semibold"
                                        onclick={refreshQrDisabledProductionData}
                                        disabled={productionProjectionLoading}
                                    >
                                        Refresh pending status
                                    </button>
                                </div>
                            {/if}
                        </section>
                    {/if}
                {/snippet}
                {#snippet cameraDeckSettingsExtras()}
                    {#if !qrDisabledMode}
                        <div class="grid gap-2 border-t border-border pt-3">
                            <span class="text-xs font-medium">QR recovery</span>
                            <select
                                class="min-w-0 rounded-md border border-border bg-background px-3 py-2 text-xs"
                                bind:value={manualQrState}
                                aria-label="QR state"
                            >
                                <option value="">No manual recovery</option>
                                <option value="absent">QR absent</option>
                                <option value="undecodable">QR unreadable</option>
                            </select>
                            <input
                                class="min-w-0 rounded-md border border-border bg-background px-3 py-2 text-xs"
                                bind:value={manualDisplayCode}
                                maxlength="24"
                                autocomplete="off"
                                placeholder="Printed display code"
                                aria-label="Printed display code"
                            />
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" bind:checked={manualIdentityConfirmed} />
                                <span>I confirm the printed display code</span>
                            </label>
                        </div>
                    {/if}
                {/snippet}
                {#snippet selectionContent()}
                    {#if screen === 'review'}
                        <section
                            class="review-screen"
                            aria-label="Sheet review"
                        >
                            {#if reviewBusy}
                                <div
                                    class="review-busy"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <RefreshCw size={16} class="spin" />
                                    <div>
                                        <strong>Analysis in progress</strong>
                                        <span
                                            >This scan is {statusLabel(
                                                selectedSheet?.status,
                                            )}. The review below refreshes
                                            automatically when analysis
                                            completes.</span
                                        >
                                    </div>
                                </div>
                            {:else if reviewFailed}
                                <div class="review-failed" role="alert">
                                    <X size={16} />
                                    <div>
                                        <strong>Analysis failed</strong>
                                        <span
                                            >No answer evidence is available for
                                            this scan. Retry the scan or replace
                                            its page.</span
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        class="table-edit"
                                        onclick={() =>
                                            retrySheet(selectedSheet)}
                                        disabled={confirmingBatch}
                                        ><RefreshCw size={13} /> Retry scan</button
                                    >
                                </div>
                            {/if}

                            {#if hasImageEvidence(selectedSheet) && reviewReady && nameRegion}
                                <div class="review-card name-card">
                                    <div class="review-card-heading">
                                        <h2>Handwritten name</h2>
                                    </div>
                                    <div class="name-crop-wrap">
                                        {#key `${selectedSheet?.version ?? ''}:${reviewImageUrl ?? ''}:${activeReviewPage}`}
                                            <OmrRegionCrop
                                                imageUrl={reviewImageUrl}
                                                region={nameRegion}
                                                alt="Cropped handwritten name evidence"
                                                width={240}
                                            />
                                        {/key}
                                        <p class="review-note">
                                            Confirm the physical handwritten
                                            name against the session roster.
                                        </p>
                                    </div>
                                </div>
                            {/if}

                            {#if selectedSheet?.recovery?.primary_action === 'compare_duplicates' && duplicateSheet}
                                <div
                                    class="review-card duplicate-disposition-card"
                                    aria-labelledby="duplicate-disposition-title"
                                >
                                    <div class="review-card-heading">
                                        <div>
                                            <h2
                                                id="duplicate-disposition-title"
                                            >
                                                Choose the scoring evidence
                                            </h2>
                                            <p>
                                                Compare both preserved captures,
                                                then record which scan survives.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="duplicate-compare">
                                        <div>
                                            <strong
                                                >Earlier scan #{duplicateSheet.id}</strong
                                            >
                                            <span
                                                >{duplicateSheet.detected_applicant_number ??
                                                    'Applicant Number unresolved'}
                                                · {confidenceLabel(
                                                    duplicateSheet.confidence,
                                                )}</span
                                            >
                                        </div>
                                        <div>
                                            <strong
                                                >Current scan #{selectedSheet.id}</strong
                                            >
                                            <span
                                                >{selectedSheet.detected_applicant_number ??
                                                    'Applicant Number unresolved'}
                                                · {confidenceLabel(
                                                    selectedSheet.confidence,
                                                )}</span
                                            >
                                        </div>
                                    </div>
                                    <label class="abstain-field">
                                        <span>Disposition reason</span>
                                        <textarea
                                            bind:value={duplicateReason}
                                            minlength="8"
                                            maxlength="500"
                                            placeholder="Record why this capture is the approved scoring evidence"
                                        ></textarea>
                                    </label>
                                    <div class="recovery-actions">
                                        <button
                                            class="primary-button"
                                            type="button"
                                            onclick={() =>
                                                resolveDuplicate(
                                                    duplicateSheet,
                                                    selectedSheet,
                                                )}
                                            disabled={resolvingDuplicate}
                                            >Keep earlier scan</button
                                        >
                                        <button
                                            class="outline-button"
                                            type="button"
                                            onclick={() =>
                                                resolveDuplicate(
                                                    selectedSheet,
                                                    duplicateSheet,
                                                )}
                                            disabled={resolvingDuplicate}
                                            >Keep current scan</button
                                        >
                                    </div>
                                </div>
                            {/if}

                            {#if identityExceptionOpen}
                                <div class="review-card">
                                    <div class="review-card-heading">
                                        <h2>Applicant exception</h2>
                                    </div>
                                    <div class="identity-exception-form">
                                        <label>
                                            <span
                                                >Session examinee assignment</span
                                            >
                                            <select
                                                bind:value={
                                                    exceptionAssignmentId
                                                }
                                            >
                                                <option value={null}
                                                    >Select an eligible examinee</option
                                                >
                                                {#each eligibleAssignments as assignment (assignment.id)}
                                                    <option
                                                        value={assignment.id}
                                                    >
                                                        {assignment.applicant_number ??
                                                            'No ID'} · {assignment.name ??
                                                            'Unassigned'}
                                                    </option>
                                                {/each}
                                            </select>
                                        </label>
                                        <label>
                                            <span>Reason for assignment</span>
                                            <textarea
                                                bind:value={exceptionReason}
                                                minlength="8"
                                                maxlength="255"
                                                placeholder="Explain why this paper is being bound to this applicant"
                                            ></textarea>
                                        </label>
                                        <button
                                            class="primary-button"
                                            type="button"
                                            onclick={resolveApplicantException}
                                            disabled={resolvingApplicant ||
                                                !exceptionAssignmentId ||
                                                exceptionReason.trim().length <
                                                    8}
                                        >
                                            {resolvingApplicant
                                                ? 'Resolving…'
                                                : 'Resolve applicant exception'}
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </section>
                    {/if}
                {/snippet}
                {#snippet queueContent()}
                    {#if screen === 'gallery'}
                        <section
                            class="gallery-screen"
                            aria-label="Scans gallery"
                        >
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">Scans</p>
                                    <h1>Captures gallery</h1>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        {gallery.length} Scanned · {needsReviewSheets.length} Needs Check
                                    </p>
                                </div>
                                <div class="heading-actions">
                                    {#if needsReviewSheets.length > 0}
                                        <button
                                            class="primary-button"
                                            type="button"
                                            onclick={openNextNeedsReview}
                                        >
                                            Next needs review ({needsReviewSheets.length})
                                        </button>
                                    {/if}
                                    {#if batch?.status === 'open' && gallery.length > 0}
                                        <button
                                            class="danger-button"
                                            type="button"
                                            onclick={rejectBatch}
                                            disabled={confirmingBatch}
                                            ><Trash2 size={14} /> Reject batch</button
                                        >
                                    {/if}
                                    <button
                                        class="icon-action focus-only"
                                        type="button"
                                        aria-label="Close gallery"
                                        onclick={openCamera}
                                        ><X size={19} /></button
                                    >
                                </div>
                            </div>
                            <div class="tabs" role="tablist" aria-label="Filter captured sheets">
                                <button
                                    class:active={activeTab === 'all'}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === 'all'}
                                    onclick={() => setActiveTab('all')}
                                    >All <b>{gallery.length}</b></button
                                >
                                <button
                                    class:active={activeTab === 'needs_review'}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === 'needs_review'}
                                    onclick={() => setActiveTab('needs_review')}
                                    >Needs Review <b>{needsReviewSheets.length}</b></button
                                >
                                <button
                                    class:active={activeTab === 'ready'}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === 'ready'}
                                    onclick={() => setActiveTab('ready')}
                                    >Ready <b>{readySheets.length}</b></button
                                >
                                <button
                                    class:active={activeTab === 'draft'}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === 'draft'}
                                    onclick={() => setActiveTab('draft')}
                                    >Draft <b>{gallery.filter((sheet) => sheet.status !== 'committed').length}</b></button
                                >
                            </div>

                            {#if operationalStatus?.needs_attention?.length > 0}
                                <section
                                    class="attention-queue"
                                    aria-label="Needs attention by responsible owner"
                                >
                                    <div class="attention-heading">
                                        <div>
                                            <strong>Needs attention</strong>
                                            <span
                                                >Repair one blocked sheet
                                                without stopping the rest of the
                                                run.</span
                                            >
                                        </div>
                                        <b
                                            >{operationalStatus.counts
                                                .needs_review +
                                                operationalStatus.counts
                                                    .failed}</b
                                        >
                                    </div>
                                    <div class="attention-groups">
                                        {#each operationalStatus.needs_attention as group (group.owner)}
                                            <button
                                                type="button"
                                                onclick={() => {
                                                    const sheet = sheets.find(
                                                        (candidate) =>
                                                            Number(
                                                                candidate.id,
                                                            ) ===
                                                            Number(
                                                                group
                                                                    .sheet_ids[0],
                                                            ),
                                                    );
                                                    if (sheet)
                                                        openReview(sheet);
                                                }}
                                            >
                                                <span
                                                    ><strong
                                                        >{group.owner}</strong
                                                    ><small
                                                        >{group.count} sheet{group.count ===
                                                        1
                                                            ? ''
                                                            : 's'} blocked</small
                                                    ></span
                                                >
                                                <b>{group.count}</b>
                                            </button>
                                        {/each}
                                    </div>
                                </section>
                            {/if}

                            <div class="scan-grid">
                                {#each visibleScans as sheet (sheet.id)}
                                    {@const info = getExamineeInfo(sheet)}
                                    <div
                                        class="scan-card"
                                        role="button"
                                        tabindex="0"
                                        onclick={() => openReview(sheet)}
                                        onkeydown={(event) => {
                                            if (
                                                event.key === 'Enter' ||
                                                event.key === ' '
                                            ) {
                                                event.preventDefault();
                                                openReview(sheet);
                                            }
                                        }}
                                    >
                                        <div class="scan-thumb">
                                            {#if hasImageEvidence(sheet)}
                                                <img
                                                    src={sheet.thumbnail_url}
                                                    alt={info.name}
                                                    class="scan-img-thumb"
                                                    loading="lazy"
                                                    decoding="async"
                                                    onerror={() =>
                                                        markImageUnavailable(
                                                            sheet,
                                                        )}
                                                />
                                            {:else}
                                                <div class="mini-paper">
                                                    <span
                                                        >{evidenceLabel(
                                                            sheet,
                                                        ) ===
                                                        'Preview placeholder'
                                                            ? 'PREVIEW'
                                                            : 'NEXIAM'}</span
                                                    >
                                                    <i></i><i class="fill"
                                                    ></i><i></i><i></i>
                                                </div>
                                            {/if}
                                            <div
                                                class="examinee-avatar"
                                                title={info.name}
                                            >
                                                {info.initials}
                                            </div>
                                            <span
                                                class="status-dot {sheet.status}"
                                                title={statusLabel(
                                                    sheet.status,
                                                )}
                                            ></span>
                                        </div>
                                        <div class="scan-meta">
                                            <div class="meta-header">
                                                <strong title={info.name}
                                                    >{info.name}</strong
                                                >
                                            </div>
                                            <span class="examinee-ref"
                                                >{info.code}</span
                                            >
                                            {#if info.observedSet}
                                                <span class="examinee-ref"
                                                    >Observed Set {info.observedSet}
                                                    · evidence only</span
                                                >
                                            {/if}
                                            {#if Number(sheet.expected_page_count ?? 1) > 1}
                                                <div
                                                    class="page-chips"
                                                    role="list"
                                                    aria-label="Sheet pages"
                                                >
                                                    {#each Array(Number(sheet.expected_page_count)) as _, index}
                                                        {@const pageNo =
                                                            index + 1}
                                                        {@const page = (
                                                            sheet.pages ?? []
                                                        ).find(
                                                            (candidate) =>
                                                                Number(
                                                                    candidate.page_no,
                                                                ) === pageNo,
                                                        )}
                                                        <span
                                                            class="page-chip"
                                                            class:captured={page?.captured}
                                                            class:missing={!page?.captured}
                                                            role="listitem"
                                                            title={`Page ${pageNo}: ${pageLabel(sheet, pageNo)}`}
                                                        >
                                                            <span
                                                                class="page-chip-dot"
                                                            ></span>
                                                            P{pageNo}
                                                        </span>
                                                    {/each}
                                                </div>
                                            {/if}
                                            <div class="meta-footer">
                                                <small
                                                    >{sheet.capture_mode ===
                                                    'auto'
                                                        ? 'Auto'
                                                        : 'Manual'} · {statusLabel(
                                                        sheet.status,
                                                    )}</small
                                                >
                                                <small class="time-stamp"
                                                    >{sheet.created_at
                                                        ? new Date(
                                                              sheet.created_at,
                                                          ).toLocaleTimeString(
                                                              [],
                                                              {
                                                                  hour: '2-digit',
                                                                  minute: '2-digit',
                                                              },
                                                          )
                                                        : 'just now'}</small
                                                >
                                            </div>
                                            {#if sheet.status === 'pending_pages' && missingPageCount(sheet) > 0}
                                                <div class="card-actions">
                                                    <button
                                                        class="card-action primary"
                                                        type="button"
                                                        onclick={(event) => {
                                                            event.stopPropagation();
                                                            resumeSet(sheet);
                                                        }}
                                                    >
                                                        <Camera size={12} /> Resume
                                                        set
                                                    </button>
                                                    {#each (sheet.pages ?? []).filter((page) => page.captured) as page (page.page_no)}
                                                        <button
                                                            class="card-action"
                                                            type="button"
                                                            title={`Retake page ${page.page_no}`}
                                                            onclick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                triggerReplace(
                                                                    sheet,
                                                                    Number(
                                                                        page.page_no,
                                                                    ),
                                                                );
                                                            }}
                                                        >
                                                            <RefreshCw
                                                                size={12}
                                                            /> Retake P{page.page_no}
                                                        </button>
                                                    {/each}
                                                </div>
                                            {/if}
                                            {#if sheet.status === 'failed_terminal'}
                                                <div class="card-actions">
                                                    <button
                                                        class="card-action primary"
                                                        type="button"
                                                        onclick={(event) => {
                                                            event.stopPropagation();
                                                            retrySheet(sheet);
                                                        }}
                                                    >
                                                        <RefreshCw size={12} /> Retry
                                                        scan
                                                    </button>
                                                </div>
                                            {/if}
                                            <div class="audit-row">
                                                <span
                                                    class="evidence-badge"
                                                    class:available={hasImageEvidence(
                                                        sheet,
                                                    )}
                                                >
                                                    {#if hasImageEvidence(sheet)}<FileImage
                                                            size={11}
                                                        />{:else}<ImageIcon
                                                            size={11}
                                                        />{/if}
                                                    {evidenceLabel(sheet)}
                                                </span>
                                                {#if sheet.identity_confidence != null}
                                                    <span
                                                        class="audit-pill"
                                                        class:high-conf={sheet.identity_confidence >=
                                                            0.8}
                                                        class:low-conf={sheet.identity_confidence <
                                                            0.8}
                                                    >
                                                        {Math.round(
                                                            sheet.identity_confidence *
                                                                100,
                                                        )}% identity match
                                                    </span>
                                                {:else}
                                                    <span
                                                        class="audit-pill neutral"
                                                        >Identity not detected</span
                                                    >
                                                {/if}
                                            </div>
                                        </div>
                                    </div>
                                {:else}
                                    <div class="empty-gallery">
                                        <ImageIcon size={30} />
                                        <strong>No scans yet</strong>
                                        <span
                                            >Capture or upload an answer sheet
                                            to start the review flow.</span
                                        >
                                        <button
                                            class="outline-button"
                                            type="button"
                                            onclick={openCamera}
                                            ><Camera size={16} /> Capture a sheet</button
                                        >
                                    </div>
                                {/each}
                            </div>

                            <button
                                class="primary-button gallery-capture focus-only"
                                type="button"
                                onclick={openCamera}
                                ><Camera size={17} /> Capture another sheet</button
                            >
                        </section>
                    {/if}
                {/snippet}
            </OmrWorkbench>
        {:else}
            <section
                class="workbench-fallback"
                role="status"
                aria-live="polite"
            >
                {#if productionProjectionLoading}
                    <RefreshCw class="spin" size={18} />
                    <strong>Loading production workbench…</strong>
                {:else}
                    <strong>Production OMR workbench unavailable</strong>
                    <p>
                        {productionProjectionError?.detail ??
                            'The production run could not be loaded.'}
                    </p>
                    <button
                        type="button"
                        class="outline-button"
                        onclick={() => loadProductionProjection()}
                    >
                        <RefreshCw size={14} /> Retry workbench
                    </button>
                {/if}
            </section>
        {/if}
        {/if}
</div>

<style>
    .omr-app {
        --omr-bg: var(--background);
        --omr-surface: var(--card);
        --omr-surface-solid: var(--card-solid);
        --omr-text: var(--foreground);
        --omr-muted: var(--muted-foreground);
        --omr-border: var(--border);
        --omr-primary: var(--primary);
        --omr-primary-soft: color-mix(
            in oklab,
            var(--primary) 12%,
            transparent
        );
        --omr-primary-strong: color-mix(
            in oklab,
            var(--primary) 22%,
            var(--background)
        );
        --omr-stage: oklch(0.27 0.04 148);
        --omr-stage-deep: oklch(0.2 0.035 148);
        --omr-stage-text: oklch(0.94 0.02 145);
        min-height: 100dvh;
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        background: var(--omr-bg);
        color: var(--omr-text);
        font-family: var(--font-sans);
        isolation: isolate;
    }
    .omr-app,
    .omr-app * {
        box-sizing: border-box;
    }

    .settings-wrap {
        position: relative;
        display: inline-flex;
    }
    .settings-backdrop {
        position: fixed;
        inset: 0;
        z-index: 4;
    }
    .settings-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        z-index: 5;
        min-width: 280px;
        max-width: min(360px, calc(100vw - 32px));
        max-height: min(70vh, 520px);
        overflow-y: auto;
        padding: 9px;
        background: var(--omr-surface-solid);
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .settings-menu-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .settings-menu-title {
        margin: 6px 0 2px;
        padding: 2px 9px;
        color: var(--omr-muted);
        font: 10px var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    .settings-menu-title:first-child {
        margin-top: 0;
    }
    .settings-refresh {
        flex: 0 0 auto;
        padding: 5px !important;
        color: var(--omr-muted) !important;
    }
    .settings-refresh:hover {
        color: var(--omr-primary) !important;
    }
    .settings-menu button span {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .settings-menu button strong,
    .settings-menu button small {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .settings-menu button small {
        color: var(--omr-muted);
        font-size: 10px;
        font-weight: 400;
        line-height: 1.3;
    }
    .settings-menu button {
        border: 0;
        background: transparent;
        color: var(--omr-text);
        text-align: left;
        padding: 8px 10px;
        border-radius: var(--radius-md);
        font-size: 12px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    .settings-menu button:hover:not(:disabled) {
        background: var(--omr-primary-soft);
        color: var(--omr-primary);
    }
    .settings-menu button.selected {
        background: var(--omr-primary-soft);
        color: var(--omr-primary);
        font-weight: 650;
    }
    .settings-menu button:disabled {
        cursor: not-allowed;
        opacity: 0.58;
    }
    .menu-hint-box {
        margin: 2px 4px 4px;
        padding: 8px 10px;
        border-radius: var(--radius-md);
        background: var(--omr-primary-soft);
        color: var(--omr-muted);
        font-size: 11px;
        line-height: 1.45;
    }
    .settings-field {
        display: grid;
        gap: 5px;
        padding: 3px 9px;
        color: var(--omr-muted);
        font-size: 11px;
    }
    .settings-field select,
    .settings-field input {
        width: 100%;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-md);
        background: var(--omr-surface);
        color: var(--omr-text);
        padding: 8px 9px;
        font: 12px var(--font-sans);
    }
    .settings-confirmation {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 9px 8px;
        color: var(--omr-text);
        font-size: 11px;
    }

    .start-screen {
        flex: 1;
        display: grid;
        place-items: center;
        padding: 32px 18px;
    }
    .start-card {
        width: min(100%, 520px);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 11px;
        text-align: center;
        background: var(--omr-surface);
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-xl);
        padding: 38px 24px;
        box-shadow: var(--shadow-lg);
    }
    .start-card > svg {
        color: var(--omr-primary);
    }
    .start-card h1 {
        margin: 0;
        color: var(--omr-text);
        font-size: 24px;
        letter-spacing: -0.02em;
    }
    .start-card > p:not(.eyebrow) {
        max-width: 410px;
        color: var(--omr-muted);
        font-size: 13px;
        line-height: 1.6;
    }
    .pair-button {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 15px;
        border: 1px solid
            color-mix(in oklab, var(--omr-primary) 38%, var(--omr-border));
        border-radius: var(--radius-md);
        background: var(--omr-primary-soft);
        color: var(--omr-text);
        text-align: left;
        cursor: pointer;
        font-size: 13px;
    }
    .pair-button:hover {
        border-color: var(--omr-primary);
        background: var(--omr-primary-strong);
    }
    .pair-button small {
        color: var(--omr-primary);
        font-weight: 650;
    }

    /* Workspace Layout & Split Mode */
    .omr-workspace {
        display: flex;
        flex-direction: column;
        flex: 1;
        width: 100%;
        min-height: 0;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .workspace-panel {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    .is-hidden {
        display: none !important;
    }
    .workbench-fallback {
        display: grid;
        place-items: center;
        gap: 0.75rem;
        min-height: 16rem;
        padding: 2rem;
        text-align: center;
    }
    .workbench-fallback p {
        max-width: 36rem;
        color: var(--omr-muted);
        font-size: 0.875rem;
    }


    .icon-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: var(--radius-md);
        padding: 8px;
        background: transparent;
        color: var(--omr-muted);
        cursor: pointer;
    }
    .icon-action:hover {
        background: var(--omr-primary-soft);
        color: var(--omr-primary);
    }

    .gallery-screen,
    .review-screen {
        width: min(calc(100% - 32px), 900px);
        margin: 0 auto;
        padding: 28px 0 38px;
        flex: 1;
    }
    .embedded-evidence {
        min-height: 620px;
        margin: 16px 0 20px;
        overflow: clip;
        border: 1px solid var(--omr-border);
        border-radius: 12px;
        background: var(--omr-surface);
    }
    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 15px;
    }
    .eyebrow {
        color: var(--omr-primary);
        font: 10px var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.12em;
        margin: 0 0 7px;
    }
    .section-heading h1 {
        color: var(--omr-text);
        font-size: clamp(21px, 4vw, 28px);
        font-weight: 650;
        margin: 0;
        letter-spacing: -0.025em;
    }
    .tabs {
        display: flex;
        gap: 6px;
        margin: 20px 0 15px;
        overflow-x: auto;
        border-bottom: 1px solid var(--omr-border);
    }
    .tabs button {
        border: 0;
        border-bottom: 2px solid transparent;
        background: transparent;
        color: var(--omr-muted);
        padding: 9px 11px;
        cursor: pointer;
        font-size: 12px;
    }
    .tabs button.active {
        color: var(--omr-primary);
        border-bottom-color: var(--omr-primary);
        font-weight: 650;
    }
    .tabs b {
        margin-left: 5px;
        font: 10px var(--font-mono);
        color: var(--omr-muted);
    }

    .attention-queue {
        margin: 0 0 16px;
        padding: 12px;
        border: 1px solid
            color-mix(in oklab, var(--warning) 40%, var(--omr-border));
        border-radius: 10px;
        background: color-mix(in oklab, var(--warning) 7%, var(--omr-surface));
    }
    .attention-heading,
    .attention-groups button {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .attention-heading strong,
    .attention-groups strong {
        display: block;
        color: var(--omr-text);
        font-size: 12px;
    }
    .attention-heading span,
    .attention-groups small {
        display: block;
        margin-top: 2px;
        color: var(--omr-muted);
        font-size: 10px;
    }
    .attention-heading > b,
    .attention-groups b {
        color: var(--warning);
        font: 700 11px var(--font-mono);
    }
    .attention-groups {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 8px;
        margin-top: 10px;
    }
    .attention-groups button {
        padding: 9px 10px;
        border: 1px solid var(--omr-border);
        border-radius: 8px;
        background: var(--omr-surface-solid);
        text-align: left;
        cursor: pointer;
    }
    .attention-groups button:hover {
        border-color: var(--warning);
    }
    /* Scan Grid & Cards */
    .scan-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 14px;
    }
    .scan-card {
        text-align: left;
        padding: 0;
        border: 1px solid var(--omr-border);
        background: var(--omr-surface);
        color: var(--omr-text);
        border-radius: var(--radius-lg);
        overflow: hidden;
        cursor: pointer;
        transition:
            border-color 0.15s,
            transform 0.15s,
            box-shadow 0.15s;
    }
    .scan-card:hover {
        border-color: var(--omr-primary);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .scan-thumb {
        height: 145px;
        background: var(--omr-primary-soft);
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .scan-img-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.2s ease;
    }
    .scan-card:hover .scan-img-thumb {
        transform: scale(1.03);
    }

    .mini-paper {
        width: 84px;
        height: 115px;
        background: oklch(0.97 0.015 87);
        color: var(--omr-primary);
        padding: 10px 8px;
        transform: rotate(-3deg);
        box-shadow: var(--shadow-md);
        font: 6px var(--font-mono);
    }
    .mini-paper i {
        display: inline-block;
        width: 9px;
        height: 9px;
        margin: 15px 4px 0 0;
        border: 1px solid oklch(0.62 0.03 145);
        border-radius: 50%;
    }
    .mini-paper i.fill {
        background: var(--omr-primary);
        border-color: var(--omr-primary);
    }

    .examinee-avatar {
        position: absolute;
        bottom: 8px;
        left: 8px;
        z-index: 2;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--omr-primary);
        color: var(--primary-foreground);
        display: flex;
        align-items: center;
        justify-content: center;
        font: 700 11px var(--font-sans);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25);
        border: 2px solid var(--omr-surface-solid);
    }

    .status-dot {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--omr-muted);
        box-shadow: 0 0 0 3px
            color-mix(in oklab, var(--omr-surface) 80%, transparent);
    }
    .status-dot.needs_review,
    .status-dot.processing,
    .status-dot.queued {
        background: var(--warning);
    }
    .status-dot.reviewed,
    .status-dot.committed {
        background: var(--success);
    }
    .status-dot.failed_terminal {
        background: var(--destructive);
    }

    .scan-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 12px 14px 14px;
    }
    .meta-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }
    .meta-header strong {
        font-size: 13px;
        font-weight: 650;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .bound-icon {
        color: var(--success);
        flex-shrink: 0;
    }
    .examinee-ref {
        color: var(--omr-primary);
        font: 11px var(--font-mono);
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .meta-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 4px;
        margin-top: 2px;
    }
    .meta-footer small {
        color: var(--omr-muted);
        font: 10px var(--font-mono);
    }
    .audit-row {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 5px;
    }
    .evidence-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 7px;
        border: 1px solid color-mix(in oklab, #b7791f 35%, var(--omr-border));
        border-radius: 999px;
        font: 600 10px var(--font-mono);
        color: #966515;
        white-space: nowrap;
    }
    .evidence-badge.available {
        border-color: color-mix(in oklab, #2f8f62 35%, var(--omr-border));
        color: #26724f;
    }
    .audit-pill {
        padding: 3px 7px;
        border-radius: 999px;
        font: 600 10px var(--font-mono);
        background: var(--omr-primary-soft);
        color: var(--omr-primary);
        white-space: nowrap;
    }
    .audit-pill.high-conf {
        background: color-mix(in oklab, #2f8f62 14%, var(--omr-surface-solid));
        color: #26724f;
    }
    .audit-pill.low-conf {
        background: color-mix(in oklab, #b7791f 16%, var(--omr-surface-solid));
        color: #966515;
    }
    .audit-pill.neutral {
        background: var(--omr-stage-deep);
        color: var(--omr-muted);
    }

    .empty-gallery {
        min-height: 270px;
        grid-column: 1 / -1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 9px;
        color: var(--omr-muted);
        text-align: center;
        border: 1px dashed var(--omr-border);
        border-radius: var(--radius-lg);
    }
    .empty-gallery strong {
        color: var(--omr-text);
        font-size: 15px;
    }
    .empty-gallery span {
        max-width: 320px;
        font-size: 12px;
        line-height: 1.5;
    }

    .primary-button,
    .outline-button,
    .danger-button {
        border-radius: var(--radius-md);
        min-height: 40px;
        padding: 0 15px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        gap: 7px;
        cursor: pointer;
        font-weight: 650;
        font-size: 12px;
    }
    .primary-button {
        color: var(--primary-foreground);
        background: var(--omr-primary);
        border: 1px solid var(--omr-primary);
    }
    .primary-button:hover {
        filter: brightness(1.08);
    }
    .outline-button {
        color: var(--omr-text);
        background: var(--omr-surface);
        border: 1px solid var(--omr-border);
    }
    .outline-button:hover {
        border-color: var(--omr-primary);
        background: var(--omr-primary-soft);
    }
    .danger-button {
        color: var(--destructive);
        background: transparent;
        border: 1px solid
            color-mix(in oklab, var(--destructive) 35%, var(--omr-border));
    }
    .gallery-capture {
        margin-top: 18px;
        width: 100%;
    }
    .confirm-button {
        margin-top: 14px;
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--omr-primary);
        border-radius: var(--radius-md);
        background: var(--omr-primary-strong);
        color: var(--omr-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
    }
    .confirm-button:disabled {
        opacity: 0.6;
        cursor: wait;
    }

    .binding-field,
    .score-summary {
        display: flex;
        flex-direction: column;
        gap: 7px;
        margin-top: 14px;
        padding: 14px;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-lg);
        background: var(--omr-surface);
        color: var(--omr-muted);
        font-size: 11px;
    }
    .binding-field span {
        color: var(--omr-text);
        font-weight: 650;
    }
    .binding-field select {
        min-height: 40px;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-md);
        background: var(--omr-surface-solid);
        color: var(--omr-text);
        padding: 0 10px;
    }
    .score-summary span:not(.eyebrow) {
        display: flex;
        justify-content: space-between;
        color: var(--omr-muted);
        font: 11px var(--font-mono);
    }
    .score-summary strong {
        color: var(--omr-text);
    }

    .review-image {
        margin-top: 18px;
        min-height: 350px;
        background: var(--omr-stage-deep);
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .review-paper {
        width: min(66%, 300px);
        aspect-ratio: 0.71;
        background: oklch(0.97 0.015 87);
        color: oklch(0.38 0.04 148);
        box-shadow: var(--shadow-xl);
        padding: 21px 18px;
        transform: rotate(-1.2deg);
    }
    .paper-brand {
        color: var(--omr-primary);
        font: 700 8px var(--font-mono);
        letter-spacing: 0.12em;
    }
    .paper-rule {
        height: 1px;
        background: oklch(0.82 0.02 145);
        margin: 16px 0;
    }
    .paper-bubbles {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px 8px;
    }
    .paper-bubbles i {
        width: 14px;
        height: 14px;
        border: 1px solid oklch(0.6 0.03 145);
        border-radius: 50%;
    }
    .paper-bubbles i.marked {
        background: var(--omr-primary);
        border-color: var(--omr-primary);
    }
    .paper-bubbles i.warning {
        background: var(--warning);
        border-color: var(--warning);
    }
    .paper-footer {
        color: oklch(0.52 0.03 148);
        font: 10px var(--font-mono);
    }
    .review-photo {
        max-width: 100%;
        max-height: 620px;
        object-fit: contain;
        border-radius: var(--radius-sm);
        box-shadow: var(--shadow-xl);
    }
    .review-photo-wrap {
        position: relative;
        display: inline-block;
        line-height: 0;
    }
    .review-photo-wrap .review-photo {
        display: block;
    }
    .overlay-layer {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }
    .ov-row {
        position: absolute;
        border: 2px solid transparent;
        border-radius: 3px;
        box-sizing: border-box;
    }
    .ov-row.correct {
        border-color: #2fdd8d;
    }
    .ov-row.incorrect {
        border-color: #ff6b68;
    }
    .ov-row.ambiguous {
        border-color: #ffc24d;
    }
    .ov-option {
        position: absolute;
        display: grid;
        place-items: center;
        border-radius: 50%;
        border: 2px solid transparent;
        background: rgba(255, 255, 255, 0.16);
        box-sizing: border-box;
    }
    .ov-option.correct {
        border-color: #2fdd8d;
    }
    .ov-option.incorrect {
        border-color: #ff6b68;
    }
    .ov-option.ambiguous {
        border-color: #ffc24d;
    }
    .ov-option b {
        font: 700 10px var(--font-mono);
        color: #fff;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.85);
    }
    .overlay-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin: 10px 2px 0;
        padding: 9px 12px;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-md);
        background: var(--omr-surface);
    }
    .legend-entry {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--omr-muted);
        font: 600 10px var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .legend-swatch {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .legend-swatch.correct {
        background: #2fdd8d;
    }
    .legend-swatch.incorrect {
        background: #ff6b68;
    }
    .legend-swatch.ambiguous {
        background: #ffc24d;
    }
    .legend-swatch.blank {
        border: 1px dashed var(--omr-muted);
        background: transparent;
    }
    .review-busy,
    .review-failed {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 12px;
        padding: 11px 13px;
        border-radius: var(--radius-md);
        font-size: 12px;
    }
    .review-busy {
        border: 1px solid color-mix(in oklab, #b7791f 42%, var(--omr-border));
        background: color-mix(in oklab, #b7791f 6%, var(--omr-surface-solid));
        color: var(--omr-text);
    }
    .review-failed {
        border: 1px solid color-mix(in oklab, #b94a48 45%, var(--omr-border));
        background: color-mix(in oklab, #b94a48 6%, var(--omr-surface-solid));
        color: var(--omr-text);
        align-items: center;
    }
    .review-busy strong,
    .review-failed strong {
        display: block;
        font-size: 12px;
    }
    .review-busy span,
    .review-failed span {
        display: block;
        margin-top: 2px;
        color: var(--omr-muted);
        font-size: 11px;
        line-height: 1.4;
    }
    .review-busy > svg {
        color: #966515;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .review-failed > svg {
        color: #a33c3a;
        flex-shrink: 0;
    }
    .review-failed .table-edit {
        margin-left: auto;
        border: 0;
        background: transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .tag-spin {
        display: inline-block;
        vertical-align: -2px;
        margin-right: 4px;
    }
    .inline-icon {
        display: inline-block;
        vertical-align: -2px;
        margin-right: 6px;
    }
    .name-crop-wrap {
        margin-top: 14px;
        max-width: 320px;
    }
    .name-crop-wrap .review-note {
        margin-top: 10px;
    }
    .overlay-label,
    .review-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .overlay-label {
        position: absolute;
        top: 12px;
        left: 12px;
        padding: 6px 9px;
        border-radius: var(--radius-md);
        background: var(--omr-primary);
        color: var(--primary-foreground);
        font-size: 11px;
        font-weight: 650;
    }

    .review-meta {
        margin: 9px 2px 0;
        color: var(--omr-muted);
        font: 10px var(--font-mono);
        letter-spacing: 0.04em;
    }
    .review-card {
        margin-top: 18px;
        padding: 18px;
        background: var(--omr-surface-solid);
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-lg);
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.04);
    }
    .review-card-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }
    .review-card-heading h2 {
        margin: 0;
        color: var(--omr-text);
        font-size: 16px;
    }
    .review-card-heading p {
        margin: 5px 0 0;
        color: var(--omr-muted);
        font-size: 12px;
        line-height: 1.45;
    }
    .edit-link,
    .table-edit {
        color: var(--omr-primary);
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }
    .edit-link:hover,
    .table-edit:hover {
        text-decoration: underline;
    }
    .identity-row {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: 16px;
    }
    .identity-crop {
        position: relative;
        flex: 0 0 96px;
        width: 96px;
        min-height: 56px;
        overflow: hidden;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-md);
        background: var(--omr-stage-deep);
        color: var(--omr-muted);
        display: grid;
        place-items: center;
        font: 10px var(--font-mono);
    }
    .identity-copy {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .identity-copy strong {
        color: var(--omr-text);
        font-size: 15px;
    }
    .identity-copy span {
        color: var(--omr-muted);
        font-size: 11px;
        line-height: 1.4;
    }
    .identity-arrow {
        flex: 0 0 auto;
        color: var(--omr-muted);
    }
    .review-note {
        margin: 14px 0 0;
        color: var(--omr-muted);
        font-size: 11px;
        line-height: 1.5;
    }
    .issue-card {
        border-color: color-mix(in oklab, #b7791f 42%, var(--omr-border));
        background: color-mix(in oklab, #b7791f 5%, var(--omr-surface-solid));
    }
    .duplicate-compare {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin: 14px 0 0;
    }
    .duplicate-compare > div {
        padding: 10px;
        border: 1px solid color-mix(in oklab, #b7791f 22%, var(--omr-border));
        border-radius: var(--radius-md);
    }
    .duplicate-compare span {
        color: var(--omr-muted);
        font-size: 10px;
    }
    .duplicate-compare strong {
        display: block;
        margin: 3px 0 0;
        color: var(--omr-text);
        font-size: 12px;
    }
    .recovery-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }
    .issue-count {
        color: #966515;
        font: 700 10px var(--font-mono);
        text-transform: uppercase;
        white-space: nowrap;
    }
    .issue-list {
        display: grid;
        gap: 8px;
        margin: 14px 0 0;
        padding: 0;
        list-style: none;
    }
    .issue-list li {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 10px;
        border: 1px solid color-mix(in oklab, #b7791f 22%, var(--omr-border));
        border-radius: var(--radius-md);
    }
    .issue-list strong {
        display: block;
        color: var(--omr-text);
        font-size: 12px;
    }
    .issue-list span {
        display: block;
        margin-top: 3px;
        color: var(--omr-muted);
        font: 10px var(--font-mono);
    }
    .issue-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }
    .abstain-field {
        display: grid;
        gap: 7px;
        margin-top: 16px;
    }
    .abstain-field span {
        color: var(--omr-text);
        font-size: 12px;
        font-weight: 650;
    }
    .abstain-field textarea {
        min-height: 72px;
        resize: vertical;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-md);
        background: var(--omr-surface-solid);
        color: var(--omr-text);
        padding: 9px 10px;
        font: 12px var(--font-sans);
    }
    .score-table-wrap {
        overflow-x: auto;
        margin-top: 14px;
    }
    .score-table {
        width: 100%;
        min-width: 540px;
        border-collapse: collapse;
        color: var(--omr-text);
        font-size: 12px;
    }
    .score-table th {
        padding: 9px 10px;
        border-bottom: 1px solid var(--omr-border);
        color: var(--omr-muted);
        font: 10px var(--font-mono);
        letter-spacing: 0.05em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .score-table td {
        padding: 12px 10px;
        border-bottom: 1px solid
            color-mix(in oklab, var(--omr-border) 70%, transparent);
        vertical-align: middle;
    }
    .review-nav-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--omr-border);
    }
    .back-gallery-btn {
        border: 0;
        background: transparent;
        color: var(--omr-primary);
        font-size: 12px;
        font-weight: 650;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0;
    }
    .back-gallery-btn:hover {
        text-decoration: underline;
    }
    .review-status-tag {
        display: inline-flex;
        align-items: center;
        font: 700 10px var(--font-mono);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 3px 8px;
        border-radius: 999px;
        background: var(--omr-primary-soft);
        color: var(--omr-primary);
    }
    .review-status-tag.reviewed,
    .review-status-tag.committed {
        background: color-mix(in oklab, #2f8f62 14%, var(--omr-surface-solid));
        color: #26724f;
    }
    .review-actions {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-top: 20px;
    }
    .hidden-input,
    .file-input {
        display: none;
    }
    .empty-review {
        margin: 15px 0 0;
        color: var(--omr-muted);
        font-size: 11px;
        line-height: 1.5;
    }

    .review-actions {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-top: 20px;
    }
    .hidden-input {
        display: none;
    }
    .spin {
        animation: omr-spin 1s linear infinite;
    }
    @keyframes omr-spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 640px) {
        .review-card {
            padding: 14px;
        }
        .review-card-heading h2 {
            font-size: 15px;
        }
        .identity-row {
            align-items: flex-start;
        }
        .identity-crop {
            flex-basis: 72px;
            width: 72px;
            min-height: 44px;
        }
        .duplicate-compare {
            grid-template-columns: 1fr;
        }
        .recovery-actions {
            flex-direction: column;
        }
        .recovery-actions button {
            width: 100%;
        }
        .review-meta {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .capture-controls {
            padding-inline: 15px;
        }
        .review-actions {
            flex-direction: column-reverse;
        }
        .review-actions button {
            width: 100%;
        }
    }

    .page-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin: 6px 0 2px;
    }
    .page-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: var(--radius-full, 999px);
        border: 1px solid var(--omr-border);
        background: var(--omr-bg, #fff);
        font-size: 10px;
        font-weight: 600;
        color: var(--omr-text);
    }
    .page-chip.captured {
        border-color: color-mix(in srgb, var(--omr-primary) 45%, transparent);
    }
    .page-chip.missing {
        border-style: dashed;
        opacity: 0.75;
    }
    .page-chip-dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--omr-muted, #9aa);
    }
    .page-chip.captured .page-chip-dot {
        background: var(--omr-primary);
    }
    .page-chip.missing .page-chip-dot {
        background: transparent;
        border: 1px dashed currentColor;
    }

    .card-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 8px 0 4px;
    }
    .card-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 9px;
        border-radius: var(--radius-full, 999px);
        border: 1px solid var(--omr-border);
        background: transparent;
        font-size: 11px;
        font-weight: 600;
        color: var(--omr-text);
        cursor: pointer;
    }
    .heading-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .danger-button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 12px;
        border-radius: var(--radius-md, 8px);
        border: 1px solid color-mix(in srgb, #dc2626 55%, transparent);
        background: transparent;
        color: #dc2626;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .danger-button:hover {
        background: color-mix(in srgb, #dc2626 10%, transparent);
    }
    .danger-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .card-action.primary {
        background: var(--omr-primary);
        border-color: var(--omr-primary);
        color: #fff;
    }
    .card-action.primary:hover {
        filter: brightness(1.06);
    }

    .review-page-tabs {
        display: flex;
        gap: 6px;
        margin-bottom: 12px;
    }
    .review-page-tabs button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: var(--radius-md, 8px);
        border: 1px solid var(--omr-border);
        background: transparent;
        font-size: 13px;
        font-weight: 600;
        color: var(--omr-text);
        cursor: pointer;
    }
    .review-page-tabs button.active {
        background: var(--omr-primary);
        border-color: var(--omr-primary);
        color: #fff;
    }
    .tab-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: var(--omr-muted, #9aa);
    }
    .tab-dot.captured {
        background: currentColor;
    }
    .tab-dot.missing {
        background: transparent;
        border: 1px dashed currentColor;
    }
    .review-identity-per-page {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
        padding: 8px 10px;
        border: 1px dashed var(--omr-border);
        border-radius: var(--radius-md, 8px);
        background: var(--omr-bg, #fff);
    }
    .per-page-identity {
        font-size: 12px;
        color: var(--omr-text);
    }
    .per-page-identity b {
        color: var(--omr-primary);
        margin-right: 4px;
    }
</style>
