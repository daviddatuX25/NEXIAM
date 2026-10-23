<script module>
    export {
        mapDiagnosticEvidenceItem,
        mapDiagnosticEvidencePage,
        mapDiagnosticEvidencePage as normalizeDiagnosticEvidencePage,
        buildDiagnosticReviewPayload,
    } from '@/lib/omrDiagnosticReviewEvidence.js';
    export {
        assertDiagnosticItemReviewAcknowledged,
    } from '@/lib/omrConsoleReview';
    export function assertDiagnosticCommandProjection(value) {
        const record =
            value && typeof value === 'object' && !Array.isArray(value)
                ? value
                : {};
        const recovery =
            record.recovery &&
            typeof record.recovery === 'object' &&
            !Array.isArray(record.recovery)
                ? record.recovery
                : {};
        const outcome =
            recovery.outcome &&
            typeof recovery.outcome === 'object' &&
            !Array.isArray(recovery.outcome)
                ? recovery.outcome
                : {};
        if (
            (outcome.kind && outcome.kind !== 'success') ||
            outcome.blocked === true ||
            outcome.stale === true ||
            outcome.invalid === true
        ) {
            throw new Error(
                `The diagnostic command was not acknowledged (${outcome.code ?? outcome.kind ?? 'failed'}).`,
            );
        }
        return value;
    }
</script>

<script>
    import { onMount, untrack } from 'svelte';
    import { usePage } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import OmrWorkbench from '@/Components/Omr/Workbench/OmrWorkbench.svelte';
    import MockShadingGuideModal from '@/Components/Omr/Diagnostics/MockShadingGuideModal.svelte';
    import OmrShadingGuideViewer from '@/Components/Omr/Diagnostics/OmrShadingGuideViewer.svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { createOmrWorkbenchClient, formatOmrWorkbenchError, isOmrWorkbenchClientError, isOmrWorkbenchValidationError } from '@/lib/omrWorkbenchClient.js';
    import {
        acceptedCaptureReferenceFromProjection,
        cameraAuthorizationForFrame,
        captureAcceptanceFromProjection,
        captureActionsFromProjection,
        captureFramePayload,
        captureInputFromFrame,
    } from '@/lib/omrCaptureWorkbenchAdapter';
    import {
        createPairedPhoneSessionClient,
        OmrCameraRequestError,
        rejoinPairedPhoneSession,
        revokePairedPhoneSession,
    } from '@/lib/omrCameraSource';
    import {
        diagnosticRecord,
        diagnosticArray,
        diagnosticText,
        diagnosticCode,
        diagnosticNumber,
        diagnosticInteger,
        diagnosticPositiveInteger,
        diagnosticFirstArray,
        diagnosticUniqueCodes,
        mapDiagnosticEvidencePage,
        buildDiagnosticReviewPayload,
    } from '@/lib/omrDiagnosticReviewEvidence.js';
    import { assertDiagnosticItemReviewAcknowledged } from '@/lib/omrConsoleReview';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
    import {
        readOmrCaptureSessionRecovery,
        saveOmrCaptureSessionRecovery,
        clearOmrCaptureSessionRecovery,
        OmrCaptureSessionRecoveryStorageError,
    } from '@/lib/omrCaptureSessionRecovery';
    import { ArrowLeft } from 'lucide-svelte';

    let {
        auth = { user: null },
        test_kit_context = null,
        acceptance_plans = [],
        mock_config = {},
        mock_guide = null,
        mock_guide_blocker = null,
        resumable_run = null,
        resumable_mock_run = null,
        setup_name = null,
    } = $props();
    const page = usePage();
    const actorId = $derived(auth?.user?.id ?? page?.props?.auth?.user?.id ?? null);
    let instanceCount = $state(
        Number(
            mock_config?.instance_count ??
                resumable_mock_run?.mock_config?.instance_count ??
                resumable_run?.mock_config?.instance_count ??
                test_kit_context?.resumable_mock_run?.mock_config?.instance_count ??
                1,
        ),
    );
    let patternMode = $state(
        mock_config?.pattern_mode ??
            resumable_mock_run?.mock_config?.pattern_mode ??
            resumable_run?.mock_config?.pattern_mode ??
            test_kit_context?.resumable_mock_run?.mock_config?.pattern_mode ??
            'guided_mix',
    );
    let scannerMode = $state('setup');
    const initialMockGuide = mock_guide ?? test_kit_context?.mock_guide ?? null;
    let guide = $state(initialMockGuide);
    let guideLoading = $state(false);
    let guideError = $state(null);
    let guideModalOpen = $state(false);
    let guideModalResolve = null;
    let guideRequestId = 0;
    const guidePages = $derived(
        (guide?.pages ?? []).map((page) => ({
            ...page,
            url:
                page?.available === false
                    ? null
                    : page?.reference_url ??
                      page?.url ??
                      (page?.reference_asset_id && selectedVersionId
                          ? `/setup/exam-materials/omr-test/runs/reference-assets/${encodeURIComponent(String(selectedVersionId))}/${encodeURIComponent(String(page.page_no))}`
                          : null),
        })),
    );
    const breadcrumbs = $derived(test_kit_context
        ? [
              { label: 'Setup', href: '/setup' },
              {
                  label: 'Test Kits',
                  href: test_kit_context.return_url ?? '/setup/exam-materials/test-kits',
              },
              { label: test_kit_context.code, href: test_kit_context.return_url },
              { label: 'OMR Mock Session' },
          ]
        : [
              { label: 'Setup', href: '/setup' },
              { label: 'Exam Materials', href: '/setup/exam-materials' },
              { label: 'OMR Mock Session' },
          ]);

    let launchCommandIdentity = null;
    let launchIdempotencyKey = null;
    const selectedVersionId =
        test_kit_context?.answer_sheet?.version_id ??
        test_kit_context?.template_version_id ??
        null;
    let projection = $state(null);
    const selectedAnswerSheet = $derived(
        test_kit_context?.answer_sheet ?? test_kit_context,
    );
    const selectedAnswerSheetQrDisabled = $derived(
        (projection?.context?.capture_target?.qr_mode ?? selectedAnswerSheet?.qr_mode ?? test_kit_context?.qr_mode) ===
            'qr_disabled' ||
            typeof projection?.context?.pending_capture?.id === 'string',
    );
    const selectedBookletId =
        test_kit_context?.booklet?.version_id ??
        test_kit_context?.booklet_version_id ??
        null;
    const selectedSetCode =
        test_kit_context?.booklet?.set_code ??
        test_kit_context?.booklet_set_code ??
        null;
    let launchedMockRun = $state(null);
    let cancelledRunPublicId = $state(null);
    const activeResumableRun = $derived.by(() => {
        const candidate =
            launchedMockRun ??
            resumable_mock_run ??
            resumable_run ??
            test_kit_context?.resumable_mock_run ??
            null;
        const id = candidate?.public_id ?? candidate?.run_public_id ?? null;
        return id && id === cancelledRunPublicId ? null : candidate;
    });

    let workbenchClient = $state(null);
    let runPublicId = $state(null);
    let selectedSheetId = $state(null);
    let starting = $state(false);
    let commandBusy = $state(false);
    let refreshing = $state(false);
    let runError = $state(null);
    let reviewDirty = $state(false);
    let scannerStage = $state('camera');
    let captureMode = $state('auto');
    let pairedCamera = null;

    const runBase = $derived(
        runPublicId
            ? `/setup/exam-materials/omr-test/runs/${encodeURIComponent(runPublicId)}`
            : null,
    );
    const sheets = $derived(
        (projection?.sheets ?? []).map((sheet) => ({
            ...sheet,
            thumbnail_url:
                diagnosticAssetUrl(asRecord(sheet.assets).input) ??
                diagnosticText(asRecord(sheet.lazy_asset_urls).input, null),
        })),
    );
    const selectedSheet = $derived(
        sheets.find((sheet) => Number(sheet.id) === Number(selectedSheetId)) ??
            sheets[sheets.length - 1] ??
            null,
    );
    const pendingCapture = $derived.by(() => {
        const candidate = asRecord(
            asRecord(projection).context,
        ).pending_capture;

        return selectedAnswerSheetQrDisabled &&
            candidate !== null &&
            typeof candidate === 'object' &&
            !Array.isArray(candidate)
            ? candidate
            : null;
    });
    const pendingCaptureExpired = $derived(
        pendingCapture?.status === 'expired' ||
            String(pendingCapture?.resolution_code ?? '')
                .toLowerCase()
                .includes('expired'),
    );
    const connectionState = $derived(
        starting || commandBusy || refreshing
            ? 'connecting'
            : runError
              ? 'error'
              : 'live',
    );
    const runErrorFeedback = $derived(
        runError
            ? formatOmrWorkbenchError(runError, 'The diagnostic run could not be refreshed.')
            : null,
    );
    const mockOutcome = $derived.by(() => {
        const context = asRecord(projection?.context);
        return (
            context.mock_outcome ??
            context.mock_summary ??
            context.diagnostic_outcome ??
            projection?.mock_outcome ??
            projection?.summary ??
            projection?.completion_summary ??
            projection?.completion ??
            activeResumableRun?.summary ??
            null
        );
    });


    $effect(() => {
        if (sheets.length === 0) {
            selectedSheetId = null;
            return;
        }

        if (
            selectedSheetId === null ||
            !sheets.some(
                (sheet) => Number(sheet.id) === Number(selectedSheetId),
            )
        ) {
            selectedSheetId = sheets[sheets.length - 1].id;
        }
    });

    const evidencePage = $derived(evidenceForSheet(selectedSheet));
    const triageInventory = $derived({
        loading: false,
        error: null,
        items: sheets.flatMap((sheet) => evidenceForSheet(sheet)?.items ?? []),
    });

    function evidenceForSheet(sheet) {
        if (!sheet || !projection) return null;

        const captureId = positiveInteger(sheet.id);
        if (captureId === null) return null;

        const evidence = asRecord(projection.evidence);
        const captures = Array.isArray(evidence.captures)
            ? evidence.captures
            : [];
        const capture = asRecord(
            captures.find(
                (candidate) =>
                    Number(asRecord(candidate).capture_id) === captureId,
            ),
        );
        const captureEvidence = asRecord(capture.evidence ?? sheet.evidence);
        const projectedItems = Array.isArray(evidence.items)
            ? evidence.items.filter(
                  (item) => Number(asRecord(item).capture_id) === captureId,
              )
            : [];
        const capturedItems =
            projectedItems.length > 0
                ? projectedItems
                : Array.isArray(captureEvidence.items)
                  ? captureEvidence.items
                  : [];
        const assetUrls = {
            input: diagnosticAssetUrl(sheet.assets?.input),
            aligned: diagnosticAssetUrl(sheet.assets?.aligned),
            overlay: diagnosticAssetUrl(sheet.assets?.overlay),
        };

        return mapDiagnosticEvidencePage({
            capture: {
                ...sheet,
                ...capture,
                id: captureId,
            },
            captureEvidence,
            items: capturedItems,
            assetUrls,
            reviewable:
                capturedItems.length > 0 &&
                projection.commands.includes('review_save'),
        });
    }

    function asRecord(value) {
        return value !== null &&
            typeof value === 'object' &&
            !Array.isArray(value)
            ? value
            : {};
    }

    function positiveInteger(value) {
        const number = Number(value);

        return Number.isSafeInteger(number) && number > 0 ? number : null;
    }

    function nonNegativeInteger(value) {
        const number = Number(value);

        return Number.isSafeInteger(number) && number >= 0 ? number : 0;
    }

    function diagnosticAssetUrl(asset) {
        const assetId = positiveInteger(asRecord(asset).id);

        return assetId !== null && runBase
            ? `${runBase}/assets/${assetId}`
            : null;
    }

    function diagnosticRunUrls(publicId) {
        const base = `/setup/exam-materials/omr-test/runs/${encodeURIComponent(publicId)}`;

        return {
            show: base,
            capture: `${base}/capture`,
            retry: (captureId) =>
                `${base}/captures/${encodeURIComponent(String(captureId))}/retry`,
            discard: (captureId) =>
                `${base}/captures/${encodeURIComponent(String(captureId))}/discard`,
            review: (itemId) =>
                `${base}/evidence/${encodeURIComponent(String(itemId))}/review`,
            compare: `${base}/compare`,
            cancel: `${base}/cancel`,
            pendingCapture: `${base}/pending-capture`,
            pendingCaptureIdentity: `${base}/pending-capture/identity`,
            complete: `${base}/complete`,
        };
    }

    function projectionFromResponse(value) {
        const record = asRecord(value);
        const nested = record.projection;

        return nested !== null &&
            typeof nested === 'object' &&
            !Array.isArray(nested)
            ? nested
            : value;
    }

    function publicIdFromProjection(value) {
        const record = asRecord(value);
        const context = asRecord(record.context);
        const candidate =
            context.public_id ?? record.public_id ?? record.run_public_id;

        return typeof candidate === 'string' && candidate.trim() !== ''
            ? candidate.trim()
            : null;
    }

    function requestError(body, fallback) {
        const record = asRecord(body);
        const nested = asRecord(record.error);
        const message =
            typeof record.message === 'string'
                ? record.message
                : typeof record.detail === 'string'
                  ? record.detail
                  : typeof record.error === 'string'
                    ? record.error
                    : typeof nested.message === 'string'
                      ? nested.message
                      : fallback;
        const error = new Error(message);
        const code =
            typeof record.code === 'string'
                ? record.code
                : typeof nested.code === 'string'
                  ? nested.code
                  : null;
        const details = asRecord(record.details);
        error.code = code;
        error.stage = diagnosticText(details.stage, null);
        error.recovery = diagnosticText(details.recovery, null);
        error.supportCode = diagnosticText(details.support_code, null);
        return error;
    }

    function errorMessage(error, fallback) {
        if (typeof error === 'string' && error.trim() !== '') {
            return error;
        }

        const record = asRecord(error);
        const nested = asRecord(record.error);
        const message =
            record.message ??
            record.detail ??
            nested.message ??
            (record.code ? `Request failed (${record.code}).` : null);

        return typeof message === 'string' && message.trim() !== ''
            ? message
            : fallback;
    }

    function evidenceVersion(value) {
        const match = String(value ?? '').match(/^v?(\d+)$/i);

        return match ? Math.max(1, Number(match[1])) : 1;
    }

    async function loadRun(publicId, prepareCancellation = false) {
        const client = createOmrWorkbenchClient({
            workbenchId: `diagnostic:${publicId}`,
            executionKind: 'diagnostic',
            urls: diagnosticRunUrls(publicId),
        });
        const acknowledgedProjection = await client.load();
        if (publicIdFromProjection(acknowledgedProjection) !== publicId) {
            throw new Error('The diagnostic run returned a mismatched run identity.');
        }
        runPublicId = publicId;
        workbenchClient = client;
        projection = acknowledgedProjection;
        selectedSheetId =
            acknowledgedProjection.sheets?.[
                acknowledgedProjection.sheets.length - 1
            ]?.id ?? null;
        reviewDirty = false;
        if (!prepareCancellation) {
            scannerMode = 'scanner';
            scannerStage = 'camera';
            if (typeof window !== 'undefined') {
                const url = new URL(window.location.href);
                url.searchParams.set('run_public_id', publicId);
                window.history.replaceState(window.history.state, '', url);
            }
            void fetchGuide(publicId);
            if (selectedAnswerSheetQrDisabled && !acknowledgedProjection.context?.pending_capture) {
                void startPendingCapture();
            }
        }
        return acknowledgedProjection;
    }

    async function startRun(resumeExisting = false) {
        if (!test_kit_context || starting || commandBusy || refreshing) {
            return null;
        }
        if (
            !resumeExisting &&
            (!selectedVersionId ||
                !Number.isSafeInteger(Number(test_kit_context.revision)))
        ) {
            runError = new Error(
                'This Test Kit is missing its exact locked answer-sheet version or current revision. Reopen it from Test Kits and resolve the setup hold.',
            );
            return null;
        }
        let scannerWindow = null;
        let resumePendingCapture = false;
        const openScannerRun = (publicId) => {
            if (!scannerWindow) return;
            const url = new URL('/setup/exam-materials/omr-test', window.location.origin);
            url.searchParams.set('test_kit_version_id', String(test_kit_context.version_id));
            url.searchParams.set('run_public_id', publicId);
            scannerWindow.location.replace(url.toString());
        };
        starting = true;
        runError = null;
        try {
            const existingPublicId =
                activeResumableRun?.public_id ??
                activeResumableRun?.run_public_id ??
                null;
            const resumableStatus = String(activeResumableRun?.status ?? 'open');
            if (resumeExisting) {
                if (
                    !existingPublicId ||
                    ['completed', 'cancelled', 'expired'].includes(resumableStatus)
                ) {
                    throw new Error('There is no active Mock session available to resume.');
                }
                const retained = await loadRun(String(existingPublicId));
                resumePendingCapture = true;
                return retained;
            }
            const guideConfig = asRecord(guide?.mock_config);
            const guideCount =
                guide?.instance_count ?? guideConfig.instance_count;
            const guidePattern =
                guide?.pattern_mode ?? guideConfig.pattern_mode;
            const manifestHash = guide?.manifest_sha256 ?? null;
            if (
                !manifestHash ||
                Number(guideCount) !== Number(instanceCount) ||
                String(guidePattern) !== patternMode
            ) {
                throw new Error(
                    'The exact shading guide for this sheet count and pattern is not acknowledged yet. Retry guide preparation before launching.',
                );
            }
            scannerWindow = window.open('about:blank', '_blank');
            if (!scannerWindow) {
                throw new Error('Allow pop-ups for this site, then launch the mock scanner again.');
            }
            scannerWindow.opener = null;
            const launchIdentity = [
                test_kit_context.version_id,
                test_kit_context.revision,
                instanceCount,
                patternMode,
                manifestHash,
            ].join(':');
            if (launchIdentity !== launchCommandIdentity) {
                launchCommandIdentity = launchIdentity;
                launchIdempotencyKey = newIdempotencyKey();
            }

            const response = await fetch(
                '/setup/exam-materials/omr-test/runs',
                {
                    method: 'POST',
                    headers: {
                        ...csrfHeaders(),
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        diagnostic_purpose: 'mock_rehearsal',
                        test_kit_version_id: Number(test_kit_context.version_id),
                        expected_revision: Number(test_kit_context.revision),
                        instance_count: Number(instanceCount),
                        pattern_mode: patternMode,
                        expected_mock_manifest_sha256: manifestHash,
                        idempotency_key: launchIdempotencyKey,
                    }),
                },
            );
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw requestError(body, 'The mock rehearsal could not be launched.');
            }
            const startProjection = projectionFromResponse(body);
            assertDiagnosticCommandProjection(startProjection);
            const publicId = publicIdFromProjection(startProjection);
            if (!publicId) {
                throw new Error('The diagnostic run did not return a public identity.');
            }
            launchedMockRun = {
                public_id: publicId,
                status: 'open',
                mock_config: { instance_count: Number(instanceCount), pattern_mode: patternMode },
            };
            openScannerRun(publicId);
            launchCommandIdentity = null;
            launchIdempotencyKey = null;
            return startProjection;
        } catch (error) {
            scannerWindow?.close();
            runError = error;
            return null;
        } finally {
            starting = false;
            if (resumePendingCapture && selectedAnswerSheetQrDisabled && !projection?.context?.pending_capture) {
                void startPendingCapture();
            }
        }
    }

    async function exitScanner() {
        if (starting || commandBusy || refreshing || !canLeaveReview()) return null;
        scannerMode = 'setup';
        const url = new URL(window.location.href);
        url.searchParams.delete('run_public_id');
        window.history.replaceState(window.history.state, '', url);
        void fetchGuide();
        return null;
    }

    function openGuide() {
        return new Promise((resolve) => {
            guideModalResolve = resolve;
            guideModalOpen = true;
        });
    }

    function closeGuide() {
        guideModalOpen = false;
        const resolve = guideModalResolve;
        guideModalResolve = null;
        resolve?.();
    }

    function localGuideFallback() {
        const source = mock_guide ?? test_kit_context?.shading_guide ?? {};
        const sourceMatchesConfig =
            Number(source.instance_count ?? source.mock_config?.instance_count) ===
                Number(instanceCount) &&
            String(source.pattern_mode ?? source.mock_config?.pattern_mode) ===
                patternMode;
        return {
            ...source,
            pages: Array.isArray(source.pages)
                ? source.pages
                : Array.isArray(test_kit_context?.pages)
                  ? test_kit_context.pages
                  : [],
            instructions: Array.isArray(source.instructions)
                ? source.instructions
                : [],
            manifest_sha256: sourceMatchesConfig
                ? source.manifest_sha256 ?? null
                : null,
            instance_count: Number(instanceCount),
            pattern_mode: patternMode,
        };
    }

    async function fetchGuide(runId = null) {
        if (!test_kit_context) return;
        const requestId = ++guideRequestId;
        guideLoading = true;
        guideError = null;
        try {
            const params = runId
                ? new URLSearchParams({ run_public_id: String(runId) })
                : new URLSearchParams({
                      test_kit_version_id: String(test_kit_context.version_id),
                      expected_revision: String(test_kit_context.revision),
                      instance_count: String(instanceCount),
                      pattern_mode: String(patternMode),
                  });
            const response = await fetch(
                `/setup/exam-materials/omr-test/runs/mock-guide?${params}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw requestError(body, 'The shading guide could not be refreshed.');
            }
            if (requestId !== guideRequestId) return;
            guide = body.mock_guide ?? body;
            if (body.manifest_sha256) {
                guide = { ...guide, manifest_sha256: body.manifest_sha256 };
            }
        } catch (error) {
            if (requestId !== guideRequestId) return;
            guide = localGuideFallback();
            guideError = error;
        } finally {
            if (requestId === guideRequestId) guideLoading = false;
        }
    }
    const guideTargetKey = $derived(
        test_kit_context?.version_id &&
            Number.isSafeInteger(Number(test_kit_context?.revision))
            ? `${test_kit_context.version_id}:${test_kit_context.revision}:${Number(instanceCount)}:${String(patternMode)}`
            : null,
    );
    let lastFetchedGuideKey =
        Array.isArray(initialMockGuide?.pages) &&
        initialMockGuide.pages.length > 0 &&
        initialMockGuide?.manifest_sha256 &&
        Number(initialMockGuide?.instance_count ?? mock_config?.instance_count ?? 1) ===
            Number(instanceCount) &&
        String(initialMockGuide?.pattern_mode ?? mock_config?.pattern_mode ?? 'guided_mix') ===
            String(patternMode) &&
        test_kit_context?.version_id &&
        Number.isSafeInteger(Number(test_kit_context?.revision))
            ? `${test_kit_context.version_id}:${test_kit_context.revision}:${Number(instanceCount)}:${String(patternMode)}`
            : null;
    $effect(() => {
        const targetKey = guideTargetKey;
        if (!targetKey || targetKey === lastFetchedGuideKey) {
            return;
        }
        lastFetchedGuideKey = targetKey;
        untrack(() => {
            void fetchGuide();
        });
    });
    onMount(() => {
        const requestedRun =
            new URLSearchParams(window.location.search).get('run_public_id') ?? null;
        if (requestedRun) {
            loadRun(String(requestedRun)).catch((error) => {
                runError = error;
                scannerMode = 'setup';
            });
        }
        const refreshTimer = window.setInterval(() => {
            if (
                scannerMode === 'scanner' &&
                !document.hidden &&
                !commandBusy &&
                !refreshing &&
                !reviewDirty &&
                (projection?.phase === 'processing' ||
                    sheets.some((sheet) => ['queued', 'processing'].includes(sheet.status)))
            ) {
                void refreshProjection();
            }
        }, 1500);
        return () => window.clearInterval(refreshTimer);
    });

    function assertDiagnosticCommandProjection(value) {
        const outcome = asRecord(asRecord(value).recovery).outcome;
        const status = asRecord(outcome);
        const kind = diagnosticText(status.kind, null);
        if (
            (kind !== null && kind !== 'success') ||
            status.blocked === true ||
            status.stale === true ||
            status.invalid === true
        ) {
            const code = diagnosticText(status.code, kind ?? 'failed');
            throw new Error(
                `The diagnostic command was not acknowledged (${code}).`,
            );
        }
    }


    async function refreshProjection() {
        if (!workbenchClient || refreshing) return null;

        refreshing = true;
        try {
            const next = await workbenchClient.load();
            projection = next;
            runError = null;

            return next;
        } catch (error) {
            runError = error;

            return null;
        } finally {
            refreshing = false;
        }
    }
    function cancellationUnavailable(current, allowBusy = false) {
        const commands = Array.isArray(current?.commands) ? current.commands : [];
        const context = asRecord(current?.context);
        const activeCapture = asRecord(context.active_capture);
        const activeSheets = Array.isArray(current?.sheets)
            ? current.sheets.some((sheet) => ['queued', 'processing'].includes(asRecord(sheet).status))
            : false;
        const localRecovery = readOmrCaptureSessionRecovery(actorId, current?.workbench_id);
        const unresolvedLocalReference = localRecovery.kind !== 'none';
        const unresolved = [
            ...diagnosticArray(context.unresolved_operations),
            ...diagnosticArray(context.pending_operations),
            ...diagnosticArray(asRecord(current?.recovery).operations),
        ];
        return (
            current?.execution_kind !== 'diagnostic' ||
            context.diagnostic_purpose !== 'mock_rehearsal' ||
            !commands.includes('diagnostic_cancel') ||
            (!allowBusy && (commandBusy || starting || refreshing)) ||
            ['processing', 'queued'].includes(activeCapture.status) ||
            activeSheets ||
            unresolvedLocalReference ||
            unresolved.length > 0
        );
    }

    async function cancelMockRun() {
        const client = workbenchClient;
        const frozen = projection;
        if (!client || !frozen || !runPublicId) return null;
        if (cancellationUnavailable(frozen)) {
            runError = new Error('Cancellation is unavailable for this Mock run while work is active or unresolved.');
            return null;
        }

        const reason = window.prompt('Reason for cancelling this Mock session (required):');
        if (reason === null) return null;
        const boundedReason = reason.trim();
        if (boundedReason.length === 0 || boundedReason.length > 500) {
            runError = new Error('Enter a cancellation reason of 1–500 characters.');
            return null;
        }
        if (!window.confirm('Cancel this Mock session? Its captures and evidence will be preserved, and it cannot be resumed.')) return null;

        commandBusy = true;
        runError = null;
        try {
            const fresh = await client.load();
            if (fresh.revision !== frozen.revision || fresh.workbench_id !== frozen.workbench_id) {
                projection = fresh;
                throw new Error('The Mock session changed before confirmation. Review the current run and try again.');
            }
            if (cancellationUnavailable(fresh, true)) {
                projection = fresh;
                throw new Error('Cancellation is unavailable while this run has active or unresolved work.');
            }
            const next = await client.diagnosticCancel(
                { reason: boundedReason, idempotency_key: newIdempotencyKey() },
                frozen.revision,
            );
            assertDiagnosticCommandProjection(next);
            projection = next;
            showPreparationAfterCancellation(publicIdFromProjection(next) ?? runPublicId);
            return next;
        } catch (error) {
            try {
                const authoritative = await client.load();
                if (authoritative.workbench_id !== frozen.workbench_id) throw error;
                projection = authoritative;
                if (authoritative.status === 'cancelled' || authoritative.context?.status === 'cancelled') {
                    showPreparationAfterCancellation(publicIdFromProjection(authoritative) ?? runPublicId);
                    runError = null;
                    return authoritative;
                }
            } catch {
                // An unavailable read is not proof that cancellation committed.
            }
            runError = error;
            return null;
        } finally {
            commandBusy = false;
        }
    }
    function showPreparationAfterCancellation(publicId) {
        cancelledRunPublicId = publicId;
        launchedMockRun = null;
        scannerMode = 'setup';
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            url.searchParams.delete('run_public_id');
            window.history.replaceState(window.history.state, '', url);
        }
        void fetchGuide();
    }
    async function cancelPrepRun() {
        const candidate = activeResumableRun;
        const publicId = candidate?.public_id ?? candidate?.run_public_id;
        if (!publicId || starting || commandBusy || refreshing) return null;
        refreshing = true;
        try {
            await loadRun(String(publicId), true);
        } catch (error) {
            runError = error;
            return null;
        } finally {
            refreshing = false;
        }
        return cancelMockRun();
    }

    async function confirmPendingIdentity(selections) {
        const publicId = runPublicId;
        const capture = pendingCapture;

        if (!publicId || !capture?.id || commandBusy) return null;

        commandBusy = true;
        runError = null;

        try {
            const response = await fetch(
                diagnosticRunUrls(publicId).pendingCaptureIdentity,
                {
                    method: 'POST',
                    headers: {
                        ...csrfHeaders(),
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        pending_capture_id: capture.id,
                        pending_capture_revision: capture.revision,
                        idempotency_key: newIdempotencyKey(),
                        selections,
                    }),
                },
            );
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw requestError(
                    body,
                    'The write-in Applicant Number could not be confirmed.',
                );
            }

            projection = projectionFromResponse(body);

            return projection;
        } catch (error) {
            runError = error;

            return null;
        } finally {
            commandBusy = false;
        }
    }

    async function startPendingCapture() {
        const client = workbenchClient;
        const publicId = runPublicId;

        if (
            !client ||
            !publicId ||
            !projection?.commands?.includes('capture_start') ||
            !selectedAnswerSheetQrDisabled ||
            pendingCapture ||
            starting ||
            commandBusy ||
            refreshing
        ) {
            return null;
        }

        commandBusy = true;
        runError = null;

        try {
            const response = await fetch(
                diagnosticRunUrls(publicId).pendingCapture,
                {
                    method: 'POST',
                    headers: {
                        ...csrfHeaders(),
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        idempotency_key: newIdempotencyKey(),
                    }),
                },
            );
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw requestError(
                    body,
                    'The diagnostic page-capture set could not be started.',
                );
            }

            const next = await client.load();
            projection = next;

            return next;
        } catch (error) {
            runError = error;

            return null;
        } finally {
            commandBusy = false;
        }
    }


    async function runCommand(operation, options = {}) {
        const client = workbenchClient;
        const current = projection;
        const propagate = options.propagate === true;

        if (!client || !current || commandBusy) {
            if (propagate) {
                throw new Error('The diagnostic run is busy or unavailable.');
            }

            return null;
        }

        commandBusy = true;
        runError = null;

        try {
            const next = await operation(client, current);
            if (typeof options.validate === 'function') {
                options.validate(current, next);
            } else {
                assertDiagnosticCommandProjection(next);
            }
            projection = next;
            if (options.selectLatest === true) {
                selectedSheetId =
                    next.sheets?.[next.sheets.length - 1]?.id ?? null;
            }

            return next;
        } catch (error) {
            runError = error;
            if (propagate) throw error;

            return null;
        } finally {
            commandBusy = false;
        }
    }

    async function captureImage(frame, operation, crop) {
        if (!frame || !projection || captureDisabled()) {
            throw new Error('The diagnostic capture target is unavailable.');
        }
        const frozenTarget = operation.target;
        const target = asRecord(asRecord(projection.context).capture_target);
        const pageNo = positiveInteger(target.page_no);
        const targetRevision = positiveInteger(target.target_revision);
        const clientCaptureId = diagnosticText(target.client_capture_id, null);
        const instanceId = diagnosticText(target.instance_id, null);
        if (
            frozenTarget.workbenchId !== projection.workbench_id ||
            frozenTarget.clientCaptureId !== clientCaptureId ||
            Number(frozenTarget.pageNo) !== pageNo ||
            Number(frozenTarget.captureRevision) !== targetRevision ||
            (frozenTarget.instanceId ?? null) !== instanceId ||
            (frozenTarget.sheetId ?? null) !== (target.sheet_id ?? null) ||
            frame.targetKey !== frozenTarget.targetKey
        ) {
            throw new Error('The capture target changed. Discard this frame and capture the current page.');
        }
        const authorization = cameraAuthorizationForFrame(frame, pairedCamera);
        if (!saveOmrCaptureSessionRecovery(actorId, projection.workbench_id, operation)) {
            throw new OmrCaptureSessionRecoveryStorageError();
        }
        let next;
        try {
            next = await runCommand(
                (client, current) => client.captureStart(
                    captureInputFromFrame(frame),
                    {
                        ...captureFramePayload(frame, operation, crop),
                        ...(instanceId !== null ? { instance_id: instanceId } : {}),
                        capture_mode: operation.mode === 'manual' ? 'manual' : 'auto',
                        ...(target.pending_capture_id ? {
                            pending_capture_id: target.pending_capture_id,
                            pending_capture_revision: target.pending_capture_revision,
                        } : {}),
                        ...(target.redo_reason ? { replacement_reason: target.redo_reason } : {}),
                    },
                    current.revision,
                    authorization,
                ),
                {
                    selectLatest: true,
                    propagate: true,
                    validate: (_current, next) => {
                        captureAcceptanceFromProjection(next, operation);
                    },
                },
            );
        } catch (error) {
            if (isOmrWorkbenchValidationError(error) ||
                isOmrWorkbenchClientError(error) && [401, 419].includes(error.status)) {
                clearOmrCaptureSessionRecovery(actorId, projection.workbench_id, operation);
            }
            throw error;
        }
        const receipt = captureAcceptanceFromProjection(next, operation);
        if (receipt.status !== 'pending') {
            clearOmrCaptureSessionRecovery(actorId, projection.workbench_id, operation);
        }
        return receipt;
    }

    async function reconcileCapture(operation) {
        if (!workbenchClient) throw new Error('Reload the diagnostic workbench before checking this capture.');
        const next = await workbenchClient.reconcileCapture(operation.operationId, operation.idempotencyKey);
        const receipt = captureAcceptanceFromProjection(next, operation);
        projection = next;
        if (receipt.status !== 'pending') {
            runError = null;
            clearOmrCaptureSessionRecovery(actorId, projection.workbench_id, operation);
        }
        return receipt;
    }

    async function createPhonePairing() {
        const camera = asRecord(asRecord(projection?.context).camera);
        if (typeof camera.create_url !== 'string') throw new Error('Phone camera pairing is not available for this run.');
        const selectedPage = asRecord(projection?.context).diagnostic_purpose === 'mock_rehearsal'
            ? null
            : positiveInteger(asRecord(asRecord(projection.context).capture_target).page_no);
        const payload = {
            workbench_id: projection.workbench_id,
            ...(selectedPage ? { capture_page_no: selectedPage } : {}),
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
                ...payload,
                takeover: true,
                expected_controller_generation: error.details.controller_generation,
            });
        }
        return pairedCamera;
    }

    async function rejoinPhonePairing(bootstrap) {
        const selectedPage = asRecord(projection?.context).diagnostic_purpose === 'mock_rehearsal'
            ? null
            : positiveInteger(asRecord(asRecord(projection?.context).capture_target).page_no);
        const next = await rejoinPairedPhoneSession(bootstrap,
            selectedPage ? { capture_page_no: selectedPage } : {});
        pairedCamera = next;
        return next;
    }

    async function revokePhonePairing(bootstrap) {
        await revokePairedPhoneSession(bootstrap);
        if (pairedCamera?.pairingId === bootstrap.pairingId) pairedCamera = null;
    }

    async function captureAction(action, lastAccepted) {
        if (!lastAccepted) return { supported: false, action, reason: 'no_accepted_capture' };
        const grant = captureActionsFromProjection(projection)[action];
        if (!grant || grant.acceptedTargetKey !== lastAccepted.operation.target.targetKey) {
            return { supported: false, action, reason: 'stale_context' };
        }
        const reason = action === 'redo_last'
            ? window.prompt('Reason for recapturing the last accepted page (the previous image remains in history):')?.trim()
            : null;
        if (action === 'redo_last' && !reason) return { supported: true, action, accepted: false, message: 'Redo cancelled; no page was replaced.' };
        await runCommand((client, current) => client.captureTransition(grant.url, {
            action,
            authorization_id: grant.authorizationId,
            accepted_operation_id: lastAccepted.operation.operationId,
            accepted_target_key: lastAccepted.operation.target.targetKey,
            idempotency_key: grant.authorizationId,
            ...(reason ? { replacement_reason: reason } : {}),
        }, current.revision), { propagate: true });
        return { supported: true, action, accepted: true };
    }
    async function retakeSheet(sheet) {
        const targetSheet = sheet ?? sheets.find((s) => s.id === selectedSheetId);
        const captureId = positiveInteger(targetSheet?.id ?? targetSheet?.capture_id);
        const grant = asRecord(targetSheet?.capture_retake);
        const expectedCaptureVersion = positiveInteger(grant.expected_capture_version);
        const expectedRevision = positiveInteger(grant.expected_revision);
        const pageNo = positiveInteger(grant.page_no);
        const sourcePageEvidenceId = positiveInteger(grant.source_page_evidence_id);
        const authorizationId = diagnosticText(grant.authorization_id, null);
        const url = diagnosticText(grant.url, null);
        const target = asRecord(grant.target);
        const targetRevision = positiveInteger(target.target_revision);
        const pendingCaptureId = grant.pending_capture_id ?? null;
        const pendingCaptureRevision = grant.pending_capture_revision ?? null;
        const pendingIdentityValid =
            pendingCaptureId === null && pendingCaptureRevision === null ||
            typeof pendingCaptureId === 'string' && pendingCaptureId.trim() !== '' &&
                positiveInteger(pendingCaptureRevision) !== null;

        if (
            captureId === null ||
            expectedCaptureVersion !== positiveInteger(targetSheet?.version ?? targetSheet?.capture_version) ||
            expectedRevision !== positiveInteger(projection?.revision) ||
            pageNo === null ||
            sourcePageEvidenceId === null ||
            authorizationId === null ||
            url === null ||
            !pendingIdentityValid ||
            target.workbench_id !== projection?.workbench_id ||
            target.page_no !== pageNo ||
            targetRevision === null ||
            typeof target.client_capture_id !== 'string' ||
            target.client_capture_id.trim() === '' ||
            typeof target.target_key !== 'string' ||
            target.target_key.trim() === '' ||
            ![target.instance_id, target.sheet_id].every((value) =>
                value === null || typeof value === 'string' || Number.isSafeInteger(value)
            )
        ) {
            runError = new Error('The selected capture has no current server-authorized retake target. Refresh the workbench and select it again.');
            return;
        }

        const promptText = `Discard capture for Page ${pageNo} and retake it? The selected attempt remains in history.`;
        if (typeof window !== 'undefined' && !window.confirm(promptText)) {
            return;
        }

        try {
            await runCommand(
                (client, current) => client.captureTransition(url, {
                    action: 'capture_retake',
                    authorization_id: authorizationId,
                    capture_id: captureId,
                    expected_capture_version: expectedCaptureVersion,
                    page_no: pageNo,
                    pending_capture_id: pendingCaptureId,
                    pending_capture_revision: pendingCaptureRevision,
                    source_page_evidence_id: sourcePageEvidenceId,
                    replacement_reason: 'Operator requested retake',
                    target: {
                        workbench_id: target.workbench_id,
                        client_capture_id: target.client_capture_id,
                        page_no: pageNo,
                        target_revision: targetRevision,
                        instance_id: target.instance_id ?? null,
                        sheet_id: target.sheet_id ?? null,
                        target_key: target.target_key,
                    },
                    idempotency_key: authorizationId,
                    expected_revision: expectedRevision,
                }, current.revision),
                { propagate: true },
            );
            scannerStage = 'camera';
        } catch {
            // runCommand retains the typed transport error for the workbench alert.
        }
    }
    async function discardSheet(sheet) {
        const targetSheet = sheet ?? sheets.find((s) => s.id === selectedSheetId);
        const captureId = positiveInteger(targetSheet?.id ?? targetSheet?.capture_id ?? selectedSheetId);
        const expectedCaptureVersion = positiveInteger(targetSheet?.version ?? targetSheet?.capture_version);
        if (captureId === null) return;
        if (expectedCaptureVersion === null) {
            runError = new Error('The selected capture version is unavailable; reload the workbench before removing it.');
            return;
        }

        const pageLabel = targetSheet?.page_no ?? targetSheet?.page ?? targetSheet?.sheet_number ?? '';
        const promptText = pageLabel
            ? `Remove capture for Page ${pageLabel} from the current queue? The original image and attempt history will remain; the server determines the next available target.`
            : 'Remove this capture from the current queue? The original image and attempt history will remain; the server determines the next available target.';
        if (typeof window !== 'undefined' && !window.confirm(promptText)) {
            return;
        }

        const url = diagnosticRunUrls(runPublicId).discard(captureId);
        try {
            await runCommand(
                (client, current) => client.captureTransition(url, {
                    expected_capture_version: expectedCaptureVersion,
                    reason: 'Discarded by operator',
                }, current.revision),
                { propagate: true },
            );
            selectedSheetId = null;
            scannerStage = 'camera';
        } catch {
            // runCommand retains the typed transport error for the workbench alert.
        }
    }



    async function retryCapture(context = {}) {
        const record = asRecord(context);
        const captureId = positiveInteger(
            record.capture_id ?? record.sheet_id ?? selectedSheetId,
        );
        if (captureId === null) return null;

        return runCommand(
            (client, current) =>
                client.captureRetry(
                    captureId,
                    { idempotency_key: newIdempotencyKey() },
                    current.revision,
                ),
            { selectLatest: true },
        );
    }

    async function saveReview(payload, row) {
        const payloadRecord = asRecord(payload);
        const rowRecord = asRecord(row);
        const itemId = positiveInteger(rowRecord.id);
        const captureId = positiveInteger(rowRecord.capture_id);
        const capture = sheets.find(
            (sheet) => Number(sheet.id) === Number(captureId),
        );

        if (itemId === null || captureId === null || !capture) {
            throw new Error(
                'The diagnostic evidence row is no longer available.',
            );
        }

        const reviewPayload = buildDiagnosticReviewPayload(
            payload,
            row,
            capture,
            String(payloadRecord.idempotency_key ?? '').trim() === ''
                ? newIdempotencyKey()
                : null,
        );

        const expectedReview = {
            decision: reviewPayload.decision,
            selected_option: reviewPayload.final_option,
            reason_code: reviewPayload.reason,
            note: reviewPayload.note,
            expected_parent_version: reviewPayload.expected_capture_version,
            expected_item_revision: reviewPayload.expected_item_revision,
            reviewed_machine_evidence_hash:
                reviewPayload.reviewed_evidence_hash,
            idempotency_key: reviewPayload.idempotency_key,
        };
        const next = await runCommand(
            (client, current) =>
                client.reviewSave(itemId, reviewPayload, current.revision),
            {
                propagate: true,
                validate: (current, refreshed) =>
                    assertDiagnosticItemReviewAcknowledged(
                        current,
                        refreshed,
                        captureId,
                        itemId,
                        expectedReview,
                    ),
            },
        );
        const acknowledgedItem = asRecord(next?.evidence).items;
        const acknowledged = Array.isArray(acknowledgedItem)
            ? acknowledgedItem
                  .map(asRecord)
                  .find(
                      (entry) =>
                          Number(entry.id) === itemId &&
                          Number(entry.capture_id) === captureId,
                  )
            : null;
        if (!acknowledged?.review) {
            throw new Error(
                'The diagnostic item review acknowledgment is missing.',
            );
        }
        return { review: acknowledged.review, projection: next };
    }

    async function handleRecoveryAction(action, recovery) {
        if (action === 'capture_retry' || action === 'retry_scan') {
            return retryCapture(recovery);
        }

        if (action === 'review_save') {
            const captureId = positiveInteger(asRecord(recovery).capture_id);
            if (captureId !== null) selectedSheetId = captureId;

            return refreshProjection();
        }

        return null;
    }

    async function handleComplete(command) {
        if (command === 'diagnostic_compare') {
            return runCommand((client, current) =>
                client.diagnosticCompare(current.revision),
            );
        }

        if (command === 'diagnostic_complete') {
            return runCommand((client, current) =>
                client.diagnosticComplete(current.revision),
            );
        }

        return null;
    }

    function canLeaveReview() {
        if (!reviewDirty || typeof window === 'undefined') return true;

        const discard = window.confirm(
            'This item review has unsaved changes. Discard them and continue?',
        );
        if (discard) reviewDirty = false;

        return discard;
    }

    function captureDisabled() {
        if (commandBusy || !projection?.commands?.includes('capture_start')) {
            return true;
        }
        const target = asRecord(asRecord(projection.context).capture_target);
        if (
            target.pending_capture_id &&
            positiveInteger(target.pending_capture_revision) &&
            positiveInteger(target.page_no)
        ) {
            return false;
        }
        if (!selectedAnswerSheetQrDisabled) return false;
        if (!pendingCapture || pendingCaptureExpired) return true;

        return pendingCapture.status !== 'pending';
    }

    function captureDisabledReason() {
        if (commandBusy) return 'A diagnostic command is already in progress.';
        if (!projection?.commands?.includes('capture_start')) {
            return 'Capture is unavailable in the current diagnostic state.';
        }
        if (!captureDisabled()) return '';
        if (selectedAnswerSheetQrDisabled && !pendingCapture) {
            return 'Start the diagnostic page-capture set before scanning.';
        }
        if (selectedAnswerSheetQrDisabled && pendingCaptureExpired) {
            return 'This diagnostic page-capture set expired. Reload it or start a new diagnostic run.';
        }
        if (selectedAnswerSheetQrDisabled && pendingCapture?.status === 'captured') {
            return pendingCapture.pairing_code === 'pairing_missing'
                ? 'All pages are captured, but applicant identity pairing is missing. Review the saved pages or use an authorized page retake.'
                : 'All pages in this set are captured. Review the saved pages or use an authorized page retake.';
        }

        return '';
    }
</script>

<svelte:head><title>OMR Mock Session · Dry Run</title></svelte:head>

{#if scannerMode === 'scanner' && projection}
    <div class="bg-background text-foreground h-dvh w-full overflow-hidden" data-omr-test-host>
        {#snippet statusContent()}
        {#if runError}
            <section class="flex flex-col gap-2 border-b border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm sm:flex-row sm:items-start sm:justify-between" role="alert" data-omr-diagnostic-error>
                <div class="min-w-0 flex-1">
                    <p>{runErrorFeedback?.message ?? 'The diagnostic run could not be refreshed.'}</p>
                    {#if runErrorFeedback?.stage}<p class="mt-1 text-xs text-muted-foreground">Stage: {runErrorFeedback.stage}</p>{/if}
                    {#if runErrorFeedback?.recovery}<p class="mt-1 text-xs">{runErrorFeedback.recovery}</p>{/if}
                    {#if runErrorFeedback?.supportCode}<p class="mt-1 text-xs text-muted-foreground">Support code: {runErrorFeedback.supportCode}</p>{/if}
                </div>
                <Button class="min-h-11 shrink-0 self-start" size="sm" variant="outline" onclick={refreshProjection} disabled={starting || commandBusy || refreshing}>Reload run</Button>
            </section>
        {/if}
        {#if projection.commands?.includes('diagnostic_cancel')}
            <section class="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2" aria-label="Mock session actions">
                <p class="text-xs text-muted-foreground">Cancel ends this Mock session without deleting its retained captures or evidence.</p>
                <Button class="min-h-11" size="sm" variant="outline" onclick={cancelMockRun} disabled={cancellationUnavailable(projection)}>Cancel Mock session</Button>
            </section>
        {/if}
        {/snippet}
        {#key projection.workbench_id}
        <OmrWorkbench
            {projection}
            {statusContent}
            {connectionState}
            bind:cameraMode={captureMode}
            onBack={exitScanner}
            sessionTitle={`${test_kit_context?.code ?? asRecord(asRecord(projection.context).mock_config).test_kit_code ?? 'Test Kit'} · Mock Session`}
            onOpenGuide={openGuide}
            {evidencePage}
            {triageInventory}
            stageView={scannerStage}
            onStageViewChange={(stage) => (scannerStage = stage)}
            {selectedSheetId}
            onSelectSheet={(sheet) => (selectedSheetId = sheet.id)}
            onRetakeSheet={retakeSheet}
            onDiscardSheet={discardSheet}
            onRetry={retryCapture}
            onReviewAction={saveReview}
            onRecoveryAction={handleRecoveryAction}
            onComplete={handleComplete}
            onDirtyChange={(dirty) => (reviewDirty = dirty)}
            onDirtyNavigation={canLeaveReview}
            cameraDeckProps={{
                onCapture: captureImage,
                onReconcile: reconcileCapture,
                onCaptureAction: captureAction,
                authorizedActions: captureActionsFromProjection(projection),
                initialAccepted: acceptedCaptureReferenceFromProjection(projection),
                cameraPairing: asRecord(asRecord(projection.context).camera).pairing_available === false
                    ? undefined
                    : { create: createPhonePairing, rejoin: rejoinPhonePairing, revoke: revokePhonePairing },
                pairingUnavailableReason: diagnosticText(asRecord(asRecord(projection.context).camera).pairing_unavailable_reason, null),
                workbenchId: projection.workbench_id,
                actorId,
                clientCaptureId: asRecord(asRecord(projection.context).capture_target).client_capture_id ?? null,
                busy: commandBusy || starting || refreshing,
                disabled: captureDisabled() ||
                    !asRecord(asRecord(projection.context).capture_target).page_no,
                disabledReason: captureDisabledReason() ||
                    'The server has not projected a capture target.',
                registrationMode: asRecord(asRecord(projection.context).capture_target).registration_mode ??
                    pendingCapture?.registration_mode ??
                    selectedAnswerSheet?.registration_mode ??
                    test_kit_context?.registration_mode ??
                    'fiducial_anchored',
                captureRevision: positiveInteger(asRecord(asRecord(projection.context).capture_target).target_revision) ?? 0,
                instanceId: asRecord(asRecord(projection.context).capture_target).instance_id ?? null,
                sheetId: asRecord(asRecord(projection.context).capture_target).sheet_id ?? null,
                authorizedReplacementOfCaptureId: asRecord(asRecord(projection.context).capture_target).replaces_capture_id ?? null,
                pageCount: positiveInteger(asRecord(asRecord(projection.context).capture_target).page_count) ??
                    positiveInteger(pendingCapture?.page_count) ??
                    positiveInteger(asRecord(asRecord(projection.context).mock_progress).page_count) ??
                    positiveInteger(selectedAnswerSheet?.page_count ?? test_kit_context?.page_count) ??
                    1,
                currentPage: positiveInteger(asRecord(asRecord(projection.context).capture_target).page_no) ??
                    positiveInteger(pendingCapture?.active_page_no) ??
                    1,
                allowUpload: true,
                singleUpload: selectedAnswerSheetQrDisabled,
            }}
        />
        {/key}
        <MockShadingGuideModal
            bind:open={guideModalOpen}
            guide={{ ...guide, pages: guidePages, marking_legend: guide?.marking_legend ?? guide?.legend }}
            instanceCount={Number(asRecord(asRecord(projection.context).mock_config).instance_count ?? instanceCount)}
            patternMode={asRecord(asRecord(projection.context).mock_config).pattern_mode ?? patternMode}
            onClose={closeGuide}
        />
    </div>
{:else}
    <StaffLayout {breadcrumbs}>
        <PageHeaderBar
            title="OMR Mock Session · Dry Run"
            description="Rehearse the exact locked Test Kit through the shared OMR scanner without creating an acceptance record."
        >
            {#snippet actions()}
                {#if test_kit_context}
                    <a href={test_kit_context.return_url ?? '/setup/exam-materials/test-kits'}>
                        <Button variant="outline" size="sm" class="gap-1.5">
                            <ArrowLeft class="size-4" /> Back to Test Kit
                        </Button>
                    </a>
                {:else}
                    <a href="/setup/exam-materials/test-kits">
                        <Button variant="outline" size="sm">Return to Test Kits</Button>
                    </a>
                {/if}
            {/snippet}
        </PageHeaderBar>
        <main class="mx-auto max-w-7xl space-y-5 px-4 pb-8 sm:px-6" data-omr-mock-setup>
            {#if runError}
                <section class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm" role="alert" data-omr-diagnostic-error>
                    {errorMessage(runError, 'The mock session could not be opened.')}
                    {#if activeResumableRun}<Button class="ml-3" size="sm" variant="outline" onclick={() => startRun(true)}>Retry resume</Button>{/if}
                </section>
            {/if}
            {#if cancelledRunPublicId}
                <p class="text-sm text-muted-foreground" role="status">
                    Mock session cancelled. Captures and evidence are preserved.
                    <a class="font-semibold underline" href={`/setup/exam-materials/omr-test?test_kit_version_id=${encodeURIComponent(String(test_kit_context?.version_id ?? ''))}&run_public_id=${encodeURIComponent(cancelledRunPublicId)}`}>View cancelled session</a>
                </p>
            {/if}
            {#if !test_kit_context}
                <section class="rounded-xl border bg-card p-6">
                    <h2 class="font-semibold">Select a Test Kit first</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Mock sessions use a pinned, locked Test Kit and its exact answer sheet, booklet, and setup. No material selectors are available here.</p>
                    <a class="mt-4 inline-flex text-sm font-semibold underline" href="/setup/exam-materials/test-kits">Return to Test Kits</a>
                </section>
            {:else}
                <section class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Pinned Test Kit context">
                    <div><p class="text-xs text-muted-foreground">Test Kit</p><p class="font-semibold">{test_kit_context.code} · {test_kit_context.version_label ?? `v${test_kit_context.version_number ?? '—'}`}</p></div>
                    <div><p class="text-xs text-muted-foreground">Answer Sheet</p><p class="font-semibold">{test_kit_context.answer_sheet?.name ?? test_kit_context.answer_sheet_code ?? test_kit_context.template_code ?? 'Unavailable'} · {test_kit_context.answer_sheet?.page_count ?? test_kit_context.page_count ?? test_kit_context.total_pages ?? '—'} pages</p></div>
                    <div><p class="text-xs text-muted-foreground">Question Booklet</p><p class="font-semibold">{test_kit_context.booklet?.name ?? test_kit_context.booklet_code ?? test_kit_context.booklet_name ?? 'Unavailable'} · {test_kit_context.booklet?.set_code ?? test_kit_context.booklet_set_code ?? 'Set unavailable'}</p></div>
                    <div><p class="text-xs text-muted-foreground">Test Setup</p><p class="font-semibold">{test_kit_context.test_setup?.name ?? setup_name ?? test_kit_context.setup_name ?? test_kit_context.test_setup_name ?? 'Unavailable'}</p></div>
                </section>
                {#if mock_guide_blocker ?? test_kit_context.mock_guide_blocker}
                    {@const blocker = asRecord(mock_guide_blocker ?? test_kit_context.mock_guide_blocker)}
                    <section class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm" role="status">
                        <strong>Mock guide/setup hold:</strong>
                        {blocker.message ?? blocker.detail ?? blocker.code ?? (typeof mock_guide_blocker === 'string' ? mock_guide_blocker : 'The pinned Kit materials are incomplete for this mock pattern.')}
                    </section>
                {/if}
                {#if activeResumableRun}
                    <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4">
                        <div><h2 class="font-semibold">An open mock session can be resumed</h2><p class="text-sm text-muted-foreground">{activeResumableRun.instance_count ?? activeResumableRun.mock_config?.instance_count ?? instanceCount} sheets · {activeResumableRun.summary?.captured_page_count ?? activeResumableRun.captured_page_count ?? 0} captured pages · {activeResumableRun.status ?? 'in progress'}</p></div>
                        <div class="flex flex-wrap gap-2">
                            <Button onclick={() => startRun(true)} disabled={starting || commandBusy || refreshing}>{starting ? 'Resuming…' : 'Resume Mock Scanner →'}</Button>
                            <Button variant="outline" onclick={cancelPrepRun} disabled={starting || commandBusy || refreshing || ['completed', 'cancelled', 'expired'].includes(String(activeResumableRun.status))}>Cancel Mock session</Button>
                        </div>
                    </section>
                {/if}
                {#if Array.isArray(mockOutcome?.instances) && mockOutcome.instances.length > 0}
                    <section class="rounded-xl border bg-card p-4" aria-label="Simulated mock scores">
                        <h2 class="font-semibold">Simulated Mock Outcomes</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Unofficial rehearsal results only. Missing norms or unresolved evidence remain unavailable rather than estimated.</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {#each mockOutcome.instances as outcome (`${outcome.instance_id ?? outcome.instance_no}`)}
                                <article class="rounded-lg border p-3">
                                    <h3 class="text-sm font-semibold">Instance #{outcome.instance_no}</h3>
                                    <p class="mt-2 text-xs font-medium">Simulated Raw</p>
                                    {#if outcome.raw_metrics}
                                        <dl class="mt-1 space-y-1 text-xs">
                                            {#each Object.entries(outcome.raw_metrics) as [metric, value] (metric)}
                                                <div class="flex justify-between gap-2"><dt class="text-muted-foreground">{metric}</dt><dd class="font-medium">{typeof value === 'object' && value !== null ? JSON.stringify(value) : value}</dd></div>
                                            {/each}
                                        </dl>
                                    {:else}
                                        <p class="text-xs text-muted-foreground">Unavailable until this instance has complete, resolved evidence.</p>
                                    {/if}
                                    <p class="mt-3 text-xs font-medium">Percentile Rank</p>
                                    {#if outcome.typed_outputs && Object.keys(outcome.typed_outputs).length > 0}
                                        <dl class="mt-1 space-y-1 text-xs">
                                            {#each Object.entries(outcome.typed_outputs) as [metric, value] (metric)}
                                                <div class="flex justify-between gap-2"><dt class="text-muted-foreground">{metric}</dt><dd class="font-medium">{typeof value === 'object' && value !== null ? (value.value ?? value.value_decimal ?? JSON.stringify(value)) : value}</dd></div>
                                            {/each}
                                        </dl>
                                    {:else}
                                        <p class="text-xs text-muted-foreground">{outcome.norm?.status === 'missing' ? 'Explicit approved norm unavailable.' : 'Percentile result unavailable.'}</p>
                                    {/if}
                                </article>
                            {/each}
                        </div>
                    </section>
                {/if}

                <section class="rounded-xl border bg-card p-4 sm:p-5">
                    <h2 class="text-base font-semibold">1. Mock Batch Setup</h2>
                    <fieldset class="mt-4 space-y-2">
                        <legend class="text-sm font-medium">Mock Sheets (Instances)</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            {#each [{ count: 1, label: '1 Sheet (Quick Check)' }, { count: 2, label: '2 Sheets (Pair)' }, { count: 3, label: '3 Sheets (Batch Triage Check)' }] as option (option.count)}
                                <button type="button" aria-pressed={instanceCount === option.count} class={`rounded-lg border p-3 text-left text-sm font-semibold ${instanceCount === option.count ? 'bg-primary/10' : ''}`} onclick={() => (instanceCount = option.count)}>{option.label}</button>
                            {/each}
                        </div>
                    </fieldset>
                    <fieldset class="mt-5 space-y-2">
                        <legend class="text-sm font-medium">Intentional Test Pattern</legend>
                        <div class="grid gap-2 lg:grid-cols-3">
                            {#each [{ value: 'guided_mix', label: 'Guided Mix (Correct + Intentional Wrong + Double-Shaded)', detail: 'Rehearses clean answers, a clean wrong answer, and an intentional ambiguous double mark.' }, { value: 'perfect_set_a', label: 'Perfect Set A Key (100% Match)', detail: 'Marks the exact finalized Set A key on every scored item.' }, { value: 'blank_baseline', label: 'Blank Baseline (0 Marks)', detail: 'Leaves every answer bubble unmarked.' }] as option (option.value)}
                                <label class="flex cursor-pointer gap-3 rounded-lg border p-3">
                                    <input type="radio" name="pattern_mode" value={option.value} bind:group={patternMode} class="mt-1" />
                                    <span><span class="block text-sm font-semibold">{option.label}</span><span class="mt-1 block text-xs text-muted-foreground">{option.detail}</span></span>
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                </section>

                <section class="space-y-3 rounded-xl border bg-card p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <h2 class="text-base font-semibold">2. Intentional Shading Guide</h2>
                                <p class="text-sm text-muted-foreground">Inspect each pinned page, highlight question sets, or click any question to locate its exact bubbles.</p>
                            </div>
                            {#if guideLoading}
                                <span class="rounded-full border border-border bg-muted/50 px-2.5 py-0.5 text-xs text-muted-foreground" role="status">
                                    Refreshing guide instructions…
                                </span>
                            {/if}
                        </div>
                        <Button variant="outline" onclick={openGuide}>Shading Guide</Button>
                    </div>
                    {#if guideError}<p class="text-xs text-amber-700 dark:text-amber-300" role="status">{errorMessage(guideError, 'Showing the available local guide while the server guide is offline.')}</p>{/if}
                    <OmrShadingGuideViewer
                        pages={guidePages}
                        instructions={guide?.instructions ?? []}
                        sections={guide?.sections ?? []}
                        instanceCount={Number(instanceCount)}
                        {patternMode}
                        markingLegend={guide?.marking_legend ?? guide?.legend ?? [
                            { intent: 'correct', label: 'Correct Set A key' },
                            { intent: 'wrong', label: 'Intentional wrong mark' },
                            { intent: 'double', label: 'Double-shaded · expect review' },
                            { intent: 'blank', label: 'Leave blank' },
                        ]}
                        outcomeSummary={guide?.outcome_summary ?? null}
                    />
                </section>
                <footer class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 py-4 backdrop-blur">
                    <p class="max-w-2xl text-xs text-muted-foreground">This mock rehearsal remains isolated from Kit acceptance, applicants, official scoring, and physical qualification.</p>
                    <Button size="lg" onclick={() => startRun()} disabled={starting || commandBusy || refreshing || guideLoading || !test_kit_context}>
                        {starting ? 'Launching mock scanner…' : 'Launch Mock Scanner →'}
                    </Button>
                </footer>
            {/if}
        </main>
        <MockShadingGuideModal bind:open={guideModalOpen} guide={guide} instanceCount={Number(instanceCount)} {patternMode} onClose={closeGuide} />
    </StaffLayout>
{/if}
