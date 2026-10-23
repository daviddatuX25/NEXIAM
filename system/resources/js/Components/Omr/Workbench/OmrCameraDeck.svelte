<script lang="ts">
    import OmrViewfinderHud from './OmrViewfinderHud.svelte';
    import OmrCropCheckModal from './OmrCropCheckModal.svelte';
    import OmrCommandCardsDialog from './OmrCommandCardsDialog.svelte';
    import { createOmrQrCommandDetector, createOmrVoiceCues } from '@/lib/omrCapturePlugins';
    import type { ViewfinderQuad, ViewfinderResult } from '@/lib/omrViewfinderWorker';
    import {
        buildOmrCaptureTargetKey,
        freezeOmrCaptureTargetContext,
        isOmrCaptureAvailable,
        isOmrFileUploadAvailable,
        type OmrBatchTrigger,
        type OmrCameraMode,
        type OmrCaptureTargetContext,
        type OmrCropPolicy,
    } from '@/lib/omrCameraAcquisition';
    import {
        createLocalOmrCameraSource,
        createPairedPhoneCameraSource,
        fetchPairedFrame,
        type OmrCameraBootstrap,
    } from '@/lib/omrCameraSource';
    import { SwitchingOmrCameraSource } from '@/lib/omrSwitchingCameraSource';
    import {
        createOmrCaptureLifecycle,
        type OmrCaptureLifecycle,
        type OmrCaptureLifecycleContext,
        type OmrCaptureLifecycleState,
        type OmrAcceptedCaptureReference,
        type OmrLifecycleAuthorization,
    } from '@/lib/omrCaptureLifecycle';
    import type {
        OmrCameraSource,
        OmrCameraSourceConfig,
        OmrCameraSourceEvent,
        OmrCaptureAcceptance,
        OmrCaptureAction,
        OmrCaptureActionResult,
        OmrCaptureCrop,
        OmrCaptureOperation,
        OmrCapturedFrame,
        OmrCapturedRawFrame,
        OmrCaptureOperationReference,
    } from '@/lib/omrCaptureProtocol';
    import {
        readOmrCaptureSessionRecovery,
        clearOmrCaptureSessionRecovery,
        purgeOmrCaptureSessionRecovery,
    } from '@/lib/omrCaptureSessionRecovery';
    import type { Snippet } from 'svelte';
    import { onDestroy, onMount, tick } from 'svelte';
    import {
        Camera,
        Check,
        FileImage,
        Image as ImageIcon,
        LayoutGrid,
        QrCode,
        RotateCcw,
        RefreshCw,
        ScanLine,
        Upload,
        Volume2,
        VolumeX,
        X,
    } from 'lucide-svelte';

    type CameraState = 'idle' | 'requesting' | 'ready' | 'unavailable';
    type CameraDeviceOption = {
        id: string;
        label: string;
        facing?: 'front' | 'back' | 'unknown';
        selectable: boolean;
    };
    type DeferredOmrUpload = {
        id: number;
        file: File;
    };
    type FailedOmrUpload = {
        id: number;
        file: File;
        frame: OmrCapturedRawFrame;
        operation: OmrCaptureOperation;
        crop: OmrCaptureCrop;
    };
    type OmrCapturePreflightGate = Readonly<{
        passed?: boolean;
        ok?: boolean;
        status?: string;
    }>;
    type OmrCapturePreflight = Readonly<{
        code?: string;
        detail?: string;
        orientation_hint?: string;
        normalized_dimensions?: Readonly<{ width: number; height: number }>;
        gates?: Readonly<Record<string, OmrCapturePreflightGate>>;
    }>;
    export type OmrCameraPairingCallbacks = Readonly<{
        create(): Promise<OmrCameraBootstrap>;
        rejoin(bootstrap: OmrCameraBootstrap): Promise<OmrCameraBootstrap>;
        revoke(bootstrap: OmrCameraBootstrap): Promise<void>;
    }>;
    export type OmrCameraDeckProps = {
        onCapture: (
            frame: OmrCapturedFrame,
            operation: OmrCaptureOperation,
            crop: OmrCaptureCrop,
        ) => Promise<OmrCaptureAcceptance>;
        onReconcile: (operation: OmrCaptureOperationReference) => Promise<OmrCaptureAcceptance>;
        onCaptureAction: (
            action: OmrCaptureAction,
            lastAccepted: OmrCaptureLifecycleState['lastAccepted'],
        ) => Promise<OmrCaptureActionResult>;
        cameraPairing?: OmrCameraPairingCallbacks;
        pairingUnavailableReason?: string | null;
        authorizedActions?: Partial<Record<OmrCaptureAction, OmrLifecycleAuthorization>>;
        initialAccepted?: OmrAcceptedCaptureReference | null;
        authorizedReplacementOfCaptureId?: string | number | null;
        onModeChange?: (mode: OmrCameraMode) => void;
        busy?: boolean;
        disabled?: boolean;
        disabledReason?: string;
        pageCount?: number;
        currentPage?: number;
        workbenchId?: string;
        actorId?: string | number | null;
        clientCaptureId?: string | null;
        registrationMode?: 'fiducial_anchored' | 'document_bound';
        captureRevision?: number | string | null;
        instanceId?: string | number | null;
        sheetId?: string | number | null;
        mode?: OmrCameraMode;
        batchTrigger?: OmrBatchTrigger;
        steadyDelayMs?: 150 | 300 | 500 | 800;
        countdownSeconds?: number;
        applyCountdownToAuto?: boolean;
        sourceKind?: 'local' | 'paired_phone';
        selectedDeviceId?: string;
        settingsOpen?: boolean;
        lastCapturedTargetKey?: string | null;
        awaitingSheetSwap?: boolean;
        cameraEnabled?: boolean;
        paused?: boolean;
        cameraCaption?: string | null;
        onOpenGallery?: (() => void) | null;
        allowUpload?: boolean;
        singleUpload?: boolean;
        preflightState?: 'idle' | 'checking' | 'validated' | 'rejected' | 'error';
        preflight?: OmrCapturePreflight | null;
        preflightError?: string;
        onResetPreflight?: () => void;
        settingsExtras?: Snippet;
    };

    let {
        onCapture,
        onReconcile,
        onCaptureAction,
        cameraPairing,
        pairingUnavailableReason = null,
        authorizedActions = {},
        initialAccepted = null,
        authorizedReplacementOfCaptureId = null,
        onModeChange,
        busy = false,
        disabled = false,
        disabledReason = 'Capture is currently unavailable.',
        pageCount = 1,
        currentPage = 1,
        workbenchId = '',
        actorId = null,
        clientCaptureId = null,
        registrationMode = 'fiducial_anchored',
        captureRevision = 0,
        instanceId = null,
        sheetId = null,
        mode = $bindable<OmrCameraMode>('auto'),
        batchTrigger = $bindable<OmrBatchTrigger>('auto_steady'),
        steadyDelayMs = $bindable<150 | 300 | 500 | 800>(300),
        countdownSeconds = $bindable(3),
        applyCountdownToAuto = $bindable(false),
        sourceKind = $bindable<'local' | 'paired_phone'>('local'),
        selectedDeviceId = $bindable<string>(''),
        settingsOpen = $bindable<boolean>(false),
        lastCapturedTargetKey = $bindable<string | null>(null),
        awaitingSheetSwap = $bindable(false),
        cameraEnabled = true,
        paused = false,
        cameraCaption = null,
        onOpenGallery = null,
        allowUpload = true,
        singleUpload = false,
        preflightState = 'idle',
        preflight = null,
        preflightError = '',
        onResetPreflight,
        settingsExtras,
    }: OmrCameraDeckProps = $props();

    let videoEl = $state<HTMLVideoElement | null>(null);
    let fileInputEl = $state<HTMLInputElement | null>(null);
    let cameraState = $state<CameraState>('idle');
    let cameraDevices = $state<CameraDeviceOption[]>([]);
    let cameraError = $state<string | null>(null);
    let fileBatchBusy = $state(false);
    let fileBatchCompleted = $state(0);
    let fileBatchTotal = $state(0);
    let fileBatchCurrent = $state(0);
    let fileBatchError = $state<string | null>(null);
    let failedFiles = $state<FailedOmrUpload[]>([]);
    let deferredFiles = $state<DeferredOmrUpload[]>([]);
    const qrDetector = createOmrQrCommandDetector();
    const voiceCues = createOmrVoiceCues();
    let voiceCuesEnabled = $state(voiceCues.enabled);
    let qrTriggerEnabled = $state(false);
    let commandCardsOpen = $state(false);
    let qrSupported = $state(false);
    let visible = $state(true);
    let videoWidth = $state(0);
    let videoHeight = $state(0);
    let cropPreviewFile = $state<File | null>(null);
    let cropPreviewError = $state<string | null>(null);
    let pairingState = $state<'unpaired' | 'creating' | 'connecting' | 'paired' | 'rejoining' | 'disconnecting' | 'error' | 'disconnected'>('unpaired');
    let pairingError = $state<string | null>(null);
    let pairingNotice = $state<string | null>(null);
    let rejoinConfirmationOpen = $state(false);
    let controllerBootstrap = $state<OmrCameraBootstrap | null>(null);
    let lifecycle = $state<OmrCaptureLifecycle | null>(null);
    let lifecycleState = $state<OmrCaptureLifecycleState | null>(null);
    let recoveryStorageIssue = $state<string | null>(null);
    let recoveryRestored = $state(false);
    let sourceSwitcher = $state<SwitchingOmrCameraSource | null>(null);
    let sourceGeneration = $state(1);
    let configRevision = $state(1);
    let sourceSwitching = $state(false);
    let physicalSwapObserved = false;
    let absentTargetKey: string | null = null;
    let absentStartedAt: number | null = null;
    let lastAbsentSequence = -1;
    let captureActionBusy = $state(false);
    let deferredUploadSequence = 0;
    let remoteConfigAcknowledged = $state(false);
    let lastContextSignature: string | null = null;
    let lastSourceSettingsSignature: string | null = null;
    let lastLifecycleSettingsSignature: string | null = null;
    let deferredAfterTargetKey = $state<string | null>(null);
    const captureTargetKey = $derived(
        buildOmrCaptureTargetKey({
            workbenchId,
            clientCaptureId,
            pageNo: currentPage,
            captureRevision,
            instanceId,
            sheetId,
        }),
    );
    const currentTarget = $derived(
        clientCaptureId === null || captureRevision === null
            ? null
            : freezeOmrCaptureTargetContext({
                workbenchId,
                clientCaptureId,
                pageNo: currentPage,
                captureRevision,
                instanceId,
                sheetId,
            }),
    );
    const submissionBusy = $derived(
        lifecycleState !== null &&
            [
                'countdown',
                'awaiting_card_removal',
                'acquiring',
                'submitting',
                'reconciling',
                'pending',
            ].includes(lifecycleState.phase),
    );
    const operationUnresolved = $derived(
        captureActionBusy ||
            lifecycleState !== null &&
                (submissionBusy ||
                    Boolean(lifecycleState.sessionRecovery) ||
                    recoveryStorageIssue !== null ||
                    lifecycleState.phase === 'frame_awaiting_review' ||
                    (lifecycleState.phase === 'rejected' && lifecycleState.retryAvailable)),
    );
    const captureAvailable = $derived(
        Boolean(
            currentTarget &&
                recoveryStorageIssue === null &&
                !lifecycleState?.sessionRecovery &&
                lifecycleState?.phase === 'armed' && !lifecycleState.awaitingSheetSwap &&
                cameraState === 'ready' &&
                isOmrCaptureAvailable(
                    {
                        cameraEnabled,
                        visible,
                        disabled,
                        busy: busy || fileBatchBusy || submissionBusy,
                        paused: paused || sourceSwitching,
                        settingsOpen,
                    },
                    currentTarget,
                ),
        ),
    );
    const uploadAvailable = $derived(
        isOmrFileUploadAvailable(
            {
                cameraEnabled,
                visible,
                disabled,
                busy: busy || submissionBusy || recoveryStorageIssue !== null || Boolean(lifecycleState?.sessionRecovery),
                paused: paused || sourceSwitching,
                settingsOpen,
            },
            currentTarget,
        ),
    );
    const viewfinderResult = $derived(lifecycleState?.quality ?? null);
    const viewfinderAvailable = $derived(
        lifecycleState !== null && lifecycleState.lastQualitySequence >= 0,
    );
    const safeSensorUrl = $derived(
        controllerBootstrap?.sensorUrl
            ? projectSafeSensorUrl(controllerBootstrap.sensorUrl)
            : null,
    );
    const preflightGateOrder = ['marker_count', 'registration', 'dimensions', 'shifts'];
    const preflightGateLabels: Record<string, string> = {
        marker_count: 'Marker count',
        registration: 'Registration',
        dimensions: 'Dimensions',
        shifts: 'Field-block shifts',
    };

    function projectSafeSensorUrl(value: string): string | null {
        try {
            const url = new URL(value, window.location.href);
            if (
                url.username ||
                url.password ||
                [...url.searchParams.keys()].some((key) =>
                    /token|secret|credential|pair_code/i.test(key),
                )
            ) {
                return null;
            }
            return url.href;
        } catch {
            return null;
        }
    }

    function isLifecyclePaused(): boolean {
        return paused || settingsOpen || sourceSwitching || !visible;
    }

    function lifecycleContext(
        cropReviewRequired = cropReviewRequiredForCamera(),
    ): OmrCaptureLifecycleContext {
        const remoteContext =
            sourceKind === 'paired_phone' && remoteConfigAcknowledged
                ? lifecycle?.getState().context
                : null;
        return {
            workbenchId,
            target: currentTarget,
            sourceGeneration:
                remoteContext?.sourceGeneration ?? sourceGeneration,
            configRevision: remoteContext?.configRevision ?? configRevision,
            registrationMode,
            pageCount,
            cameraEnabled,
            visible,
            paused: isLifecyclePaused(),
            ready: Boolean(currentTarget && !disabled && !busy),
            physicalSwapObserved,
            cropReviewRequired,
            settingsActionsAvailable: settingsOpen && !paused && visible,
            authorizedActions,
            authorizedReplacementOfCaptureId,
        };
    }

    function cropReviewRequiredForCamera(): boolean {
        return registrationMode === 'document_bound' &&
            (mode === 'manual' || (mode === 'batch' && batchTrigger === 'manual_qr'));
    }

    function contextInputSignature(): string {
        return JSON.stringify([
            captureTargetKey,
            registrationMode,
            pageCount,
            cameraEnabled,
            disabled,
            busy,
            visible,
            paused,
            settingsOpen,
            sourceSwitching,
            sourceKind,
            cropReviewRequiredForCamera(),
            JSON.stringify(authorizedActions),
        ]);
    }

    function sourceSettingsSignature(): string {
        return JSON.stringify([
            captureTargetKey,
            registrationMode,
            pageCount,
            mode,
            batchTrigger,
            steadyDelayMs,
            qrTriggerEnabled,
            sourceGeneration,
            cameraEnabled,
        ]);
    }

    function lifecycleSettingsSignature(): string {
        return JSON.stringify([
            mode,
            batchTrigger,
            countdownSeconds,
            applyCountdownToAuto,
            steadyDelayMs,
            qrTriggerEnabled,
        ]);
    }

    function activeSourceGeneration(): number {
        if (sourceKind === 'paired_phone' && remoteConfigAcknowledged) {
            return lifecycle?.getState().context.sourceGeneration ?? sourceGeneration;
        }
        return sourceGeneration;
    }
    function sourceConfigForSwitch(): OmrCameraSourceConfig | null {
        const context = lifecycleContext();
        if (!context.target) return null;
        return {
            target: context.target,
            sourceGeneration: context.sourceGeneration,
            configRevision: context.configRevision,
            registrationMode: context.registrationMode,
            pageCount: context.pageCount,
            mode,
            batchTrigger,
            steadyDelayMs,
            qrTriggerEnabled,
            paused: true,
        };
    }

    $effect(() => {
        const runtime = lifecycle;
        if (!runtime) {
            return;
        }
        const contextSignature = contextInputSignature();
        if (lastContextSignature !== contextSignature) {
            lastContextSignature = contextSignature;
            runtime.updateContext(lifecycleContext());
        }

        const sourceSignature = sourceSettingsSignature();
        if (lastSourceSettingsSignature !== sourceSignature) {
            if (
                lastSourceSettingsSignature !== null &&
                sourceKind === 'local'
            ) {
                configRevision += 1;
                runtime.updateContext(lifecycleContext());
            }
            lastSourceSettingsSignature = sourceSignature;
        }

        const settingsSignature = lifecycleSettingsSignature();
        if (lastLifecycleSettingsSignature !== settingsSignature) {
            lastLifecycleSettingsSignature = settingsSignature;
            runtime.updateSettings({
                mode,
                batchTrigger,
                countdownSeconds,
                applyCountdownToAuto,
                steadyDelayMs,
                qrTriggerEnabled,
            });
        }
    });

    $effect(() => {
        sourceSwitcher?.attachPreview(videoEl);
    });
    $effect(() => {
        const switcher = sourceSwitcher;
        if (!switcher || sourceKind !== 'local') return;
        if (!cameraEnabled && switcher.id !== 'local:unavailable') {
            void replaceCameraSource(
                createUnavailableSource(
                    'Camera access is disabled for this workbench. Use file upload instead.',
                ),
                'local',
            );
        } else if (cameraEnabled && switcher.id === 'local:unavailable') {
            void replaceCameraSource(createLocalSource(), 'local');
        }
    });

    $effect(() => {
        const currentKey = currentTarget?.targetKey ?? null;
        if (absentTargetKey !== currentKey) {
            absentTargetKey = currentKey;
            absentStartedAt = null;
            lastAbsentSequence = -1;
        }
        if (
            deferredFiles.length > 0 &&
            failedFiles.length === 0 &&
            lifecycleState?.phase !== 'pending' &&
            lifecycleState?.phase !== 'reconciling' &&
            lifecycleState?.awaitingSheetSwap !== true &&
            lifecycleState?.lastAccepted?.receipt.targetKey !== currentKey &&
            !(lifecycleState?.phase === 'rejected' && lifecycleState.retryAvailable) &&
            (deferredAfterTargetKey === null ||
                currentKey !== deferredAfterTargetKey) &&
            uploadAvailable &&
            !fileBatchBusy
        ) {
            void processDeferredUploads();
        }
    });

    $effect(() => {
        const frameState = lifecycleState;
        if (frameState?.phase !== 'frame_awaiting_review' || !frameState.frame) {
            cropPreviewFile = null;
            cropPreviewError = null;
            return;
        }
        if (frameState.frame.kind === 'raw') {
            cropPreviewFile = frameState.frame.file;
            cropPreviewError = null;
            return;
        }
        const bootstrap = controllerBootstrap;
        const stagedFrame = frameState.frame;
        if (!bootstrap || bootstrap.pairingId !== stagedFrame.pairingId) {
            cropPreviewFile = null;
            cropPreviewError = 'The private staged original is not available for crop review.';
            return;
        }
        let cancelled = false;
        cropPreviewFile = null;
        cropPreviewError = null;
        void fetchPairedFrame(stagedFrame.previewUrl, {
            role: 'controller',
            token: bootstrap.token,
            generation: bootstrap.generation,
        })
            .then((blob) => {
                if (cancelled) return;
                cropPreviewFile = new File(
                    [blob],
                    `omr-staged-preview-${stagedFrame.frameId}.jpg`,
                    { type: blob.type || 'image/jpeg' },
                );
            })
            .catch(() => {
                if (!cancelled) {
                    cropPreviewError =
                        'The private staged original preview could not be loaded. No capture was submitted.';
                }
            });
        return () => {
            cancelled = true;
        };
    });

    function handleSourceEvent(event: OmrCameraSourceEvent): void {
        if (event.type === 'configured') {
            if (sourceKind === 'paired_phone') {
                remoteConfigAcknowledged = true;
                pairingError = null;
            }
            return;
        }
        if (event.type === 'status') {
            if (event.status === 'connecting') {
                cameraState = 'requesting';
                cameraError = event.message ?? null;
                if (sourceKind === 'paired_phone') {
                    pairingState = 'connecting';
                }
            } else if (event.status === 'ready') {
                cameraState = 'ready';
                cameraError = null;
                qrSupported = sourceSwitcher?.supportsQrCommands ?? false;
                if (sourceKind === 'paired_phone') {
                    pairingState = 'paired';
                    pairingError = null;
                }
                void refreshCameraDevices();
            } else if (event.status === 'error' || event.status === 'disconnected') {
                cameraState = 'unavailable';
                cameraError =
                    event.message ??
                    (event.status === 'disconnected'
                        ? 'The camera source disconnected. Reconnect it before capture.'
                        : 'The camera source is unavailable.');
                if (sourceKind === 'paired_phone') {
                    pairingState = 'disconnected';
                    pairingError = cameraError;
                }
                qrSupported = sourceSwitcher?.supportsQrCommands ?? false;
            }
            return;
        }
        if (event.type === 'quality') {
            notePhysicalSwapObservation(event);
        }
    }

    function notePhysicalSwapObservation(
        event: Extract<OmrCameraSourceEvent, { type: 'quality' }>,
    ): void {
        const activeGeneration = activeSourceGeneration();
        const activeConfigRevision =
            lifecycle?.getState().context.configRevision ?? configRevision;
        if (
            event.sourceGeneration !== activeGeneration ||
            event.configRevision !== activeConfigRevision ||
            event.targetKey !== currentTarget?.targetKey ||
            !event.result ||
            event.result.quad !== null ||
            (event.relativeAgeMs !== undefined && event.relativeAgeMs > 500)
        ) {
            absentStartedAt = null;
            lastAbsentSequence = event.sequence;
            return;
        }
        if (event.sequence <= lastAbsentSequence) {
            return;
        }
        const now = performance.now();
        absentStartedAt ??= now;
        lastAbsentSequence = event.sequence;
        if (now - absentStartedAt < 500) {
            return;
        }
        const runtime = lifecycle;
        if (!runtime || !runtime.getState().awaitingSheetSwap) {
            absentStartedAt = null;
            return;
        }
        physicalSwapObserved = true;
        runtime.updateContext(lifecycleContext());
        physicalSwapObserved = false;
        runtime.updateContext(lifecycleContext());
        absentStartedAt = null;
    }


    function onLifecycleState(state: OmrCaptureLifecycleState): void {
        if (state.context.paused || !state.operation) voiceCues.cancel();
        lifecycleState = state;
        awaitingSheetSwap = state.awaitingSheetSwap;
        lastCapturedTargetKey = state.lastAccepted?.receipt.targetKey ?? null;
    }
    async function reconcileCaptureOperation(
        operation: OmrCaptureOperationReference,
    ): Promise<OmrCaptureAcceptance> {
        const receipt = await onReconcile(operation);
        if (receipt.status !== 'pending') {
            if (!clearOmrCaptureSessionRecovery(actorId, workbenchId, operation)) {
                recoveryStorageIssue = 'The server returned a final outcome, but its saved recovery reference could not be cleared. Do not capture again; reload the scanner when session storage is available.';
                throw new Error(recoveryStorageIssue);
            }
            if (recoveryRestored) window.location.reload();
        }
        return receipt;
    }

    function createUnavailableSource(reason: string): OmrCameraSource {
        return {
            id: 'local:unavailable',
            kind: 'local',
            async connect(listener) {
                listener({ type: 'status', status: 'error', message: reason });
            },
            attachPreview() {},
            async configure() {},
            async acquire() {
                throw new Error(reason);
            },
            disconnect() {},
        };
    }

    function createLocalSource(): OmrCameraSource {
        if (!cameraEnabled) {
            return createUnavailableSource(
                'Camera access is disabled for this workbench. Use file upload instead.',
            );
        }
        return createLocalOmrCameraSource({
            qrDetector,
            videoConstraints: selectedDeviceId
                ? {
                      deviceId: { exact: selectedDeviceId },
                      width: { ideal: 1920 },
                      height: { ideal: 1080 },
                  }
                : {
                      facingMode: { ideal: 'environment' },
                      width: { ideal: 1920 },
                      height: { ideal: 1080 },
                  },
        });
    }

    async function cameraActionAdapter(
        action: OmrCaptureAction,
        _authorization: OmrLifecycleAuthorization,
        lastAccepted: OmrCaptureLifecycleState['lastAccepted'],
    ): Promise<OmrCaptureActionResult> {
        captureActionBusy = true;
        try {
            return await onCaptureAction(action, lastAccepted);
        } finally {
            captureActionBusy = false;
        }
    }
    function resetPreflight(): void {
        if (fileBatchBusy) return;
        if (lifecycleState && (
            lifecycleState.recoveryOperation ||
            lifecycleState.sessionRecovery === 'storage_unavailable' ||
            lifecycleState.sessionRecovery === 'reconcile_required' ||
            ['submitting', 'reconciling', 'pending'].includes(lifecycleState.phase)
        )) return;
        const rejectedOperation = lifecycleState?.phase === 'rejected' && lifecycleState.retryAvailable
            ? lifecycleState.operation
            : null;
        const matchingFailure = rejectedOperation
            ? failedFiles.some((failed) => failed.operation.operationId === rejectedOperation.operationId)
            : false;
        if (matchingFailure && deferredFiles.length > 0 &&
            !window.confirm('Discard the remaining queued files and retake this page?')) {
            return;
        }
        if (rejectedOperation) {
            lifecycle?.cancel();
            failedFiles = failedFiles.filter((failed) => failed.operation.operationId !== rejectedOperation.operationId);
            if (matchingFailure && deferredFiles.length > 0) {
                deferredFiles = [];
                deferredAfterTargetKey = null;
                fileBatchCompleted = 0;
                fileBatchTotal = 0;
                fileBatchCurrent = 0;
            }
            if (matchingFailure) {
                fileBatchError = null;
            }
        }
        purgeOmrCaptureSessionRecovery(actorId, workbenchId);
        lifecycle?.dismissRecovery();
        onResetPreflight?.();
    }

    function initializeCaptureRuntime(): void {
        if (sourceSwitcher || typeof window === 'undefined') {
            return;
        }
        const savedRecovery = readOmrCaptureSessionRecovery(actorId, workbenchId);
        const initialRecovery = savedRecovery.kind === 'ready' ? savedRecovery.operation : null;
        recoveryRestored = savedRecovery.kind === 'ready';
        recoveryStorageIssue = savedRecovery.kind === 'corrupt' || savedRecovery.kind === 'unavailable'
            ? savedRecovery.message
            : null;
        const source = createLocalSource();
        sourceKind = 'local';
        sourceSwitcher = new SwitchingOmrCameraSource(
            source,
            handleSourceEvent,
        );
        sourceSwitcher.attachPreview(videoEl);
        if (!cameraEnabled) {
            cameraState = 'unavailable';
            cameraError =
                'Camera access is disabled for this workbench. Use file upload instead.';
        }
        lifecycle = createOmrCaptureLifecycle({
            source: sourceSwitcher,
            adapter: {
                submit: onCapture,
                reconcile: reconcileCaptureOperation,
                requestAction: cameraActionAdapter,
            },
            context: lifecycleContext(),
            initialAccepted,
            initialRecovery,
            initialRecoveryError: savedRecovery.kind === 'ready' || savedRecovery.kind === 'none' ? null : savedRecovery.message,
            settings: {
                mode,
                batchTrigger,
                countdownSeconds,
                applyCountdownToAuto,
                steadyDelayMs,
                qrTriggerEnabled,
            },
            onState: onLifecycleState,
            onCue: (cue) => voiceCues.emit(cue),
        });
        lastContextSignature = contextInputSignature();
        lastSourceSettingsSignature = sourceSettingsSignature();
        lastLifecycleSettingsSignature = lifecycleSettingsSignature();
    }

    async function refreshCameraDevices(): Promise<void> {
        if (
            typeof navigator === 'undefined' ||
            !navigator.mediaDevices?.enumerateDevices
        ) {
            cameraDevices = [];
            return;
        }
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            cameraDevices = devices
                .filter((device) => device.kind === 'videoinput')
                .map((device, index) => ({
                    id: device.deviceId || `camera-${index}`,
                    label: device.label || `Camera ${index + 1}`,
                    facing: deviceFacing(device, index),
                    selectable: Boolean(device.deviceId),
                }));
        } catch {
            cameraDevices = [];
        }
    }

    function deviceFacing(
        device: MediaDeviceInfo,
        index: number,
    ): 'front' | 'back' | 'unknown' {
        const label = (device.label || '').toLowerCase();
        if (
            label.includes('front') ||
            label.includes('user') ||
            label.includes('selfie')
        ) {
            return 'front';
        }
        if (
            label.includes('back') ||
            label.includes('environment') ||
            label.includes('rear')
        ) {
            return 'back';
        }
        return index === 0 ? 'back' : 'unknown';
    }

    function shortDeviceLabel(device: CameraDeviceOption, index: number): string {
        const label = device.label.toLowerCase();
        if (label.includes('ultra') || label.includes('0.5x')) return '0.5x Wide';
        if (label.includes('tele') || label.includes('3x') || label.includes('2x')) return '3x Tele';
        if (label.includes('wide') || label.includes('1x') || label.includes('main')) return '1x Main';
        if (device.facing === 'front') return 'Selfie';
        if (label.includes('usb') || label.includes('uvc') || label.includes('scanner')) return 'USB Cam';
        return `Cam ${index + 1}`;
    }

    function hasProjectedCameraRevisions(
        bootstrap: OmrCameraBootstrap,
    ): boolean {
        return (
            Number.isSafeInteger(bootstrap.config?.sourceGeneration) &&
            Number.isSafeInteger(bootstrap.config?.configRevision)
        );
    }

    async function replaceCameraSource(
        source: OmrCameraSource,
        nextKind: 'local' | 'paired_phone',
    ): Promise<void> {
        const switcher = sourceSwitcher;
        if (!switcher) {
            source.disconnect();
            return;
        }
        const previousRemoteContext =
            sourceKind === 'paired_phone' && remoteConfigAcknowledged
                ? lifecycle?.getState().context
                : null;
        sourceSwitching = true;
        lifecycle?.pause('Camera source changed. Waiting for the selected source.');
        sourceKind = nextKind;
        remoteConfigAcknowledged = false;
        if (nextKind === 'paired_phone') {
            const bootstrap = controllerBootstrap;
            const serverSourceGeneration =
                previousRemoteContext?.sourceGeneration ??
                bootstrap?.config?.sourceGeneration;
            const serverConfigRevision =
                previousRemoteContext?.configRevision ??
                bootstrap?.config?.configRevision;
            if (
                !bootstrap ||
                !Number.isSafeInteger(serverSourceGeneration) ||
                !Number.isSafeInteger(serverConfigRevision)
            ) {
                source.disconnect();
                pairingState = 'error';
                pairingError =
                    'The server did not provide authoritative camera source and configuration revisions.';
                sourceSwitching = false;
                lifecycle?.updateContext(lifecycleContext());
                return;
            }
            sourceGeneration = serverSourceGeneration as number;
            configRevision = serverConfigRevision as number;
        } else {
            sourceGeneration += 1;
            configRevision += 1;
        }
        absentStartedAt = null;
        try {
            await switcher.replace(source, sourceConfigForSwitch());
        } catch (error: unknown) {
            cameraState = 'unavailable';
            cameraError =
                error instanceof Error
                    ? error.message
                    : 'The selected camera source could not connect.';
        } finally {
            sourceSwitching = false;
            qrSupported = switcher.supportsQrCommands;
            lifecycle?.updateContext(lifecycleContext());
        }
    }

    async function switchDevice(deviceId: string): Promise<void> {
        if (
            deviceId === selectedDeviceId ||
            !cameraEnabled ||
            paused ||
            disabled ||
            sourceKind !== 'local'
        ) {
            return;
        }
        selectedDeviceId = deviceId;
        await replaceCameraSource(createLocalSource(), 'local');
    }

    async function retryCameraConnection(): Promise<void> {
        if (!cameraEnabled || paused || disabled || sourceKind !== 'local') {
            return;
        }
        await replaceCameraSource(createLocalSource(), 'local');
    }

    function requestPairedPhoneRejoin(): void {
        if (operationUnresolved) {
            pairingError =
                'Reconcile or finish the current capture operation before replacing the phone connection.';
            return;
        }
        rejoinConfirmationOpen = true;
        pairingError = null;
    }

    function cancelPairedPhoneRejoin(): void {
        rejoinConfirmationOpen = false;
    }

    async function confirmPairedPhoneRejoin(): Promise<void> {
        const bootstrap = controllerBootstrap;
        if (
            !bootstrap ||
            !cameraPairing ||
            !cameraEnabled ||
            operationUnresolved
        ) {
            return;
        }
        pairingState = 'rejoining';
        pairingError = null;
        pairingNotice = null;
        try {
            const renewedBootstrap = await cameraPairing.rejoin(bootstrap);
            if (!hasProjectedCameraRevisions(renewedBootstrap)) {
                throw new Error(
                    'The rejoined phone did not receive authoritative camera source and configuration revisions.',
                );
            }
            controllerBootstrap = renewedBootstrap;
            remoteConfigAcknowledged = false;
            rejoinConfirmationOpen = false;
            await replaceCameraSource(
                createPairedPhoneCameraSource(renewedBootstrap, {
                    qrDetector,
                }),
                'paired_phone',
            );
        } catch (error: unknown) {
            pairingState = 'error';
            pairingError =
                error instanceof Error
                    ? error.message
                    : 'The phone connection could not be replaced.';
        }
    }

    async function connectPairedPhone(): Promise<void> {
        if (!cameraPairing || !cameraEnabled) {
            pairingError = 'Phone pairing is not available for this workbench.';
            pairingState = 'error';
            return;
        }
        if (controllerBootstrap) {
            requestPairedPhoneRejoin();
            return;
        }
        pairingState = 'creating';
        pairingError = null;
        pairingNotice = null;
        try {
            const bootstrap = await cameraPairing.create();
            controllerBootstrap = bootstrap;
            if (!hasProjectedCameraRevisions(bootstrap)) {
                throw new Error(
                    'The server did not provide authoritative camera source and configuration revisions.',
                );
            }
            pairingState = 'connecting';
            await replaceCameraSource(
                createPairedPhoneCameraSource(bootstrap, { qrDetector }),
                'paired_phone',
            );
        } catch (error: unknown) {
            pairingState = 'error';
            pairingError =
                error instanceof Error
                    ? error.message
                    : 'The phone camera could not be paired.';
        }
    }

    async function disconnectPairedPhone(): Promise<void> {
        const bootstrap = controllerBootstrap;
        if (!bootstrap || !cameraPairing) {
            await replaceCameraSource(createLocalSource(), 'local');
            controllerBootstrap = null;
            pairingState = 'unpaired';
            return;
        }
        pairingState = 'disconnecting';
        pairingError = null;
        pairingNotice = null;
        await replaceCameraSource(createLocalSource(), 'local');
        try {
            await cameraPairing.revoke(bootstrap);
            if (controllerBootstrap?.pairingId === bootstrap.pairingId) {
                controllerBootstrap = null;
            }
            pairingState = 'unpaired';
        } catch (error: unknown) {
            pairingState = 'error';
            pairingError =
                error instanceof Error
                    ? `Local camera is active, but the phone pairing could not be revoked: ${error.message}`
                    : 'Local camera is active, but the phone pairing could not be revoked. Retry disconnect.';
        }
    }

    async function copyPairCode(): Promise<void> {
        const code = controllerBootstrap?.pairCode;
        if (!code) return;
        try {
            await navigator.clipboard.writeText(code);
            pairingNotice = 'Pair code copied.';
        } catch {
            pairingNotice = 'Copy is unavailable. Enter the displayed code on the phone.';
        }
    }

    function setVoiceCues(enabled: boolean): void {
        voiceCuesEnabled = enabled;
        voiceCues.setEnabled(enabled);
    }

    function setSteadyDelay(delay: 150 | 300 | 500 | 800): void {
        steadyDelayMs = delay;
    }

    function setCountdownSeconds(seconds: number): void {
        countdownSeconds = seconds;
    }

    function setMode(nextMode: OmrCameraMode): void {
        if (mode === nextMode) return;
        mode = nextMode;
        onModeChange?.(nextMode);
    }

    function cancelCapture(): void {
        lifecycle?.cancel();
    }


    async function sha256(file: File): Promise<string> {
        if (!globalThis.crypto?.subtle) {
            throw new Error('This browser cannot verify the selected original file.');
        }
        const digest = await globalThis.crypto.subtle.digest(
            'SHA-256',
            await file.arrayBuffer(),
        );
        return Array.from(new Uint8Array(digest), (byte) =>
            byte.toString(16).padStart(2, '0'),
        ).join('');
    }

    async function createUploadedFrame(
        file: File,
        target: OmrCaptureTargetContext,
        generation: number,
    ): Promise<OmrCapturedRawFrame> {
        if (file.size < 1) {
            throw new Error(`${file.name} is empty.`);
        }
        let uploadFile = file;
        let width: number | null = null;
        let height: number | null = null;
        let orientation: 1 | null = null;
        if (typeof globalThis.createImageBitmap === 'function' && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type.toLowerCase())) {
            const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            try {
                width = bitmap.width;
                height = bitmap.height;
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d');
                if (!context) throw new Error('The selected image could not be normalized.');
                context.drawImage(bitmap, 0, 0);
                const blob = await new Promise<Blob>((resolve, reject) => {
                    canvas.toBlob((value) => value ? resolve(value) : reject(new Error('The selected image could not be normalized.')), file.type);
                });
                uploadFile = new File([blob], file.name, { type: blob.type || file.type, lastModified: file.lastModified });
                orientation = 1;
            } finally {
                bitmap.close();
            }
        }
        return {
            kind: 'raw',
            file: uploadFile,
            targetKey: target.targetKey,
            inputHash: await sha256(uploadFile),
            width,
            height,
            orientation,
            sourceGeneration: generation,
        };
    }

    type UploadOutcome =
        | Readonly<{ status: 'accepted'; frame: OmrCapturedRawFrame; operation: OmrCaptureOperation; crop: OmrCaptureCrop }>
        | Readonly<{ status: 'rejected'; frame: OmrCapturedRawFrame; operation: OmrCaptureOperation; crop: OmrCaptureCrop }>
        | Readonly<{ status: 'uncertain'; frame: OmrCapturedRawFrame; operation: OmrCaptureOperation; crop: OmrCaptureCrop }>
        | Readonly<{ status: 'cancelled' }>
        | Readonly<{ status: 'blocked' }>;

    function waitForOperation(operationId: string): Promise<'accepted' | 'rejected' | 'uncertain' | 'cancelled'> {
        const runtime = lifecycle;
        if (!runtime) return Promise.resolve('cancelled');
        return new Promise((resolve) => {
            let settled = false;
            let unsubscribe = () => {};
            const finish = (result: 'accepted' | 'rejected' | 'uncertain' | 'cancelled') => {
                if (settled) return;
                settled = true;
                unsubscribe();
                resolve(result);
            };
            unsubscribe = runtime.subscribe((state) => {
                if (state.phase === 'disposed' || state.operation?.operationId !== operationId) {
                    finish('cancelled');
                    return;
                }
                if (state.phase === 'accepted') finish('accepted');
                else if (state.phase === 'rejected') finish('rejected');
                else if (state.phase === 'pending' || state.phase === 'reconciling') {
                    finish('uncertain');
                }
            });
        });
    }

    async function submitFile(
        file: File,
        target: OmrCaptureTargetContext,
        generation: number,
        requireCropReview: boolean,
    ): Promise<UploadOutcome> {
        const runtime = lifecycle;
        if (!runtime || !uploadAvailable || target.targetKey !== currentTarget?.targetKey || generation !== activeSourceGeneration()) {
            return { status: 'blocked' };
        }
        let frame: OmrCapturedRawFrame;
        try {
            frame = await createUploadedFrame(file, target, generation);
        } catch (error: unknown) {
            fileBatchError =
                error instanceof Error ? error.message : 'The selected original file could not be verified.';
            return { status: 'blocked' };
        }
        if (!uploadAvailable || target.targetKey !== currentTarget?.targetKey || generation !== activeSourceGeneration()) {
            return { status: 'blocked' };
        }
        runtime.updateContext(lifecycleContext(requireCropReview));
        if (!runtime.acceptFrame(frame, 'shutter')) {
            return { status: 'blocked' };
        }
        runtime.updateContext(lifecycleContext());
        const current = runtime.getState();
        const operation = current.operation;
        const crop = current.crop;
        if (!operation || !crop) {
            return { status: 'blocked' };
        }
        const status = await waitForOperation(operation.operationId);
        if (status === 'cancelled') return { status: 'cancelled' };
        const settled = runtime.getState();
        return {
            status,
            frame,
            operation,
            crop:
                settled.operation?.operationId === operation.operationId
                    ? settled.crop ?? crop
                    : crop,
        };
    }

    function createUploadAttempt(): Readonly<{
        target: OmrCaptureTargetContext;
        sourceGeneration: number;
    }> | null {
        if (!currentTarget || !uploadAvailable) return null;
        return { target: currentTarget, sourceGeneration: activeSourceGeneration() };
    }

    function saveFailedUpload(
        frame: OmrCapturedRawFrame,
        operation: OmrCaptureOperation,
        crop: OmrCaptureCrop,
    ): void {
        failedFiles = [
            ...failedFiles,
            {
                id: ++deferredUploadSequence,
                file: frame.file,
                frame,
                operation,
                crop,
            },
        ];
    }

    function deferUploads(
        files: File[],
        afterTargetKey: string | null = null,
    ): void {
        deferredFiles = [
            ...deferredFiles,
            ...files.map((file) => ({
                id: ++deferredUploadSequence,
                file,
            })),
        ];
        deferredAfterTargetKey = afterTargetKey;
    }

    function displayUploadFailure(
        outcome: Exclude<UploadOutcome, { status: 'blocked' }>,
        filename: string,
    ): void {
        if (outcome.status === 'rejected') {
            saveFailedUpload(outcome.frame, outcome.operation, outcome.crop);
            fileBatchError =
                `${filename} was rejected. Retry the same original before continuing.`;
        }
    }

    async function processDeferredUploads(): Promise<void> {
        if (
            fileBatchBusy ||
            deferredFiles.length === 0 ||
            !uploadAvailable ||
            failedFiles.length > 0 ||
            lifecycleState?.phase === 'pending' ||
            lifecycleState?.phase === 'reconciling' ||
            lifecycleState?.awaitingSheetSwap === true ||
            lifecycleState?.lastAccepted?.receipt.targetKey === currentTarget?.targetKey ||
            (lifecycleState?.phase === 'rejected' && lifecycleState.retryAvailable) ||
            (deferredAfterTargetKey !== null &&
                currentTarget?.targetKey === deferredAfterTargetKey)
        ) {
            return;
        }
        deferredAfterTargetKey = null;
        fileBatchBusy = true;
        fileBatchError = null;
        try {
            while (deferredFiles.length > 0) {
                if (!uploadAvailable) {
                    fileBatchError =
                        'Deferred uploads are waiting for the capture target to become available.';
                    break;
                }
                const deferred = deferredFiles[0];
                const attempt = createUploadAttempt();
                if (!deferred || !attempt) {
                    fileBatchError =
                        'Deferred uploads are waiting for a valid capture target.';
                    break;
                }
                fileBatchCurrent += 1;
                const outcome = await submitFile(
                    deferred.file,
                    attempt.target,
                    attempt.sourceGeneration,
                    false,
                );
                deferredFiles = deferredFiles.slice(1);
                if (outcome.status !== 'accepted') {
                    if (outcome.status === 'cancelled') {
                        deferUploads([deferred.file]);
                    } else if (outcome.status !== 'blocked') {
                        displayUploadFailure(outcome, deferred.file.name);
                    } else {
                        deferUploads([deferred.file]);
                        fileBatchError =
                            'The deferred original is waiting for a valid capture target.';
                    }
                    break;
                }
                fileBatchCompleted += 1;
                await tick();
                if (deferredFiles.length > 0) {
                    deferredAfterTargetKey = outcome.operation.target.targetKey;
                    fileBatchError =
                        'Remaining files are waiting for the next authorized capture target.';
                    break;
                }
            }
        } finally {
            fileBatchBusy = false;
        }
    }

    async function handleFileChange(event: Event): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const initialAttempt = createUploadAttempt();
        const files = input.files ? Array.from(input.files) : [];
        if (
            !allowUpload ||
            !uploadAvailable ||
            fileBatchBusy ||
            !initialAttempt
        ) {
            input.value = '';
            return;
        }
        if (singleUpload && files.length > 1) {
            fileBatchError =
                'This capture accepts one page image at a time. Select one file.';
            input.value = '';
            return;
        }
        const selectedFiles = singleUpload ? files.slice(0, 1) : files;
        if (selectedFiles.length === 0) {
            input.value = '';
            return;
        }
        if (failedFiles.length > 0 || deferredFiles.length > 0) {
            fileBatchError =
                'Retry the failed original and finish its deferred files before starting another batch.';
            input.value = '';
            return;
        }

        fileBatchError = null;
        fileBatchCompleted = 0;
        fileBatchCurrent = 0;
        fileBatchTotal = selectedFiles.length;
        const requiresCropReview =
            selectedFiles.length === 1 &&
            registrationMode === 'document_bound' &&
            mode === 'manual';
        fileBatchBusy = true;
        try {
            for (let index = 0; index < selectedFiles.length; index++) {
                if (!uploadAvailable) {
                    deferUploads(selectedFiles.slice(index));
                    fileBatchError =
                        'Uploads are waiting for the capture target to become available.';
                    break;
                }
                const attempt =
                    index === 0 ? initialAttempt : createUploadAttempt();
                if (!attempt) {
                    deferUploads(selectedFiles.slice(index));
                    fileBatchError =
                        'Uploads are waiting for a valid capture target.';
                    break;
                }
                const file = selectedFiles[index];
                fileBatchCurrent = index + 1;
                const outcome = await submitFile(
                    file,
                    attempt.target,
                    attempt.sourceGeneration,
                    requiresCropReview,
                );
                if (outcome.status === 'cancelled') {
                    break;
                }
                if (outcome.status !== 'accepted') {
                    if (outcome.status === 'blocked') {
                        deferUploads(selectedFiles.slice(index));
                        fileBatchError ??=
                            `${file.name} is waiting for a valid capture target.`;
                    } else {
                        const remaining = selectedFiles.slice(index + 1);
                        if (remaining.length > 0) {
                            deferUploads(
                                remaining,
                                outcome.operation.target.targetKey,
                            );
                        }
                        displayUploadFailure(outcome, file.name);
                        if (outcome.status === 'uncertain') {
                            deferredAfterTargetKey = outcome.operation.target.targetKey;
                        }
                    }
                    break;
                }
                fileBatchCompleted += 1;
                await tick();
                if (index + 1 < selectedFiles.length) {
                    deferUploads(
                        selectedFiles.slice(index + 1),
                        outcome.operation.target.targetKey,
                    );
                    fileBatchError =
                        'Remaining files are waiting for the next authorized capture target.';
                    break;
                }
            }
        } finally {
            fileBatchBusy = false;
            input.value = '';
        }
    }

    async function retryFailedFile(failed: FailedOmrUpload): Promise<void> {
        const runtime = lifecycle;
        const state = runtime?.getState();
        if (
            !runtime ||
            fileBatchBusy ||
            !uploadAvailable ||
            state?.operation?.operationId !== failed.operation.operationId ||
            state.frame?.kind !== 'raw' ||
            state.frame.inputHash !== failed.frame.inputHash
        ) {
            fileBatchError =
                'Retry is unavailable until the exact original operation is current and its target is ready.';
            return;
        }
        fileBatchBusy = true;
        fileBatchError = null;
        let accepted = false;
        try {
            const retry = runtime.retry();
            const pending = waitForOperation(failed.operation.operationId);
            await retry;
            const outcome = runtime.getState().lastAccepted?.operation.operationId === failed.operation.operationId
                ? 'accepted'
                : await pending;
            if (outcome === 'accepted') {
                failedFiles = failedFiles.filter((item) => item.id !== failed.id);
                fileBatchCompleted += 1;
                accepted = true;
            } else if (outcome !== 'uncertain') {
                fileBatchError =
                    `Could not upload ${failed.file.name}. Retry the same original when the target is ready.`;
            }
        } finally {
            fileBatchBusy = false;
        }
        if (accepted) {
            await tick();
            await processDeferredUploads();
        }
    }

    async function retryAndReconcile(): Promise<void> {
        const runtime = lifecycle;
        const operationId = lifecycleState?.operation?.operationId ?? lifecycleState?.recoveryOperation?.operationId;
        if (!runtime || !operationId) return;
        await runtime.retry();
        if (runtime.getState().lastAccepted?.operation.operationId !== operationId) return;
        if (failedFiles.some((failed) => failed.operation.operationId === operationId)) {
            failedFiles = failedFiles.filter((failed) => failed.operation.operationId !== operationId);
            fileBatchCompleted += 1;
        }
        fileBatchError = null;
        if (deferredFiles.length > 0) await processDeferredUploads();
    }

    function triggerFileUpload(): void {
        if (
            !allowUpload ||
            fileBatchBusy ||
            !uploadAvailable
        ) {
            return;
        }
        if (failedFiles.length > 0 || deferredFiles.length > 0) {
            fileBatchError =
                'Retry the failed original and finish its deferred files before starting another batch.';
            return;
        }
        if (!createUploadAttempt()) {
            return;
        }
        fileInputEl?.click();
    }

    async function finishCropCheck(
        quad: ViewfinderQuad | null,
        policy: OmrCropPolicy,
    ): Promise<void> {
        if (lifecycleState?.phase !== 'frame_awaiting_review') return;
        await lifecycle?.confirmCrop({ policy, quad });
    }

    function cancelCropCheck(): void {
        lifecycle?.cancel();
    }

    async function retryCropPreview(): Promise<void> {
        const state = lifecycleState;
        const bootstrap = controllerBootstrap;
        if (
            state?.phase !== 'frame_awaiting_review' ||
            state.frame?.kind !== 'staged' ||
            !bootstrap ||
            bootstrap.pairingId !== state.frame.pairingId
        ) {
            return;
        }
        cropPreviewError = null;
        try {
            const blob = await fetchPairedFrame(state.frame.previewUrl, {
                role: 'controller',
                token: bootstrap.token,
                generation: bootstrap.generation,
            });
            cropPreviewFile = new File(
                [blob],
                `omr-staged-preview-${state.frame.frameId}.jpg`,
                { type: blob.type || 'image/jpeg' },
            );
        } catch {
            cropPreviewError =
                'The private staged original preview could not be loaded. No capture was submitted.';
        }
    }

    function handleShortcut(event: KeyboardEvent): void {
        if (
            event.defaultPrevented ||
            event.repeat ||
            event.ctrlKey ||
            event.metaKey ||
            event.altKey ||
            document.hidden ||
            !uploadAvailable ||
            submissionBusy ||
            fileBatchBusy
        ) {
            return;
        }
        const target = event.target;
        if (
            target instanceof HTMLElement &&
            target.closest(
                'input, textarea, select, button, a, [contenteditable="true"], [role="dialog"]',
            )
        ) {
            return;
        }
        if (document.querySelector('[role="dialog"], dialog[open]')) {
            return;
        }
        if (event.key === ' ' && captureAvailable) {
            event.preventDefault();
            lifecycle?.trigger('keyboard');
        } else if (event.key.toLowerCase() === 'u' && allowUpload && uploadAvailable) {
            event.preventDefault();
            triggerFileUpload();
        }
    }

    function onVideoMetadata(): void {
        videoWidth = videoEl?.videoWidth ?? 0;
        videoHeight = videoEl?.videoHeight ?? 0;
    }

    onMount(() => {
        visible = !document.hidden;
        initializeCaptureRuntime();
        const onVisibilityChange = () => {
            visible = !document.hidden;
        };
        document.addEventListener('visibilitychange', onVisibilityChange);
        return () => {
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    });

    onDestroy(() => {
        lifecycle?.dispose();
        qrDetector.dispose();
        voiceCues.dispose();
    });

</script>

<svelte:window onkeydown={handleShortcut} />

<div class="omr-camera-deck omr-app" data-omr-camera-deck>
    {#if settingsOpen}
        <section class="capture-settings" aria-label="Capture settings">
            <div class="settings-heading">
                <h2>Capture settings</h2>
                <button
                    type="button"
                    class="settings-close"
                    aria-label="Close capture settings"
                    onclick={() => (settingsOpen = false)}
                >Close</button>
            </div>
            <div class="settings-group">
                <span class="settings-label">Camera source</span>
                <div class="settings-choice-row" role="group" aria-label="Camera source">
                    <button
                        type="button"
                        class:active={sourceKind === 'local'}
                        aria-pressed={sourceKind === 'local'}
                        disabled={sourceKind === 'local' || operationUnresolved || disabled || paused || !cameraEnabled}
                        onclick={() => void replaceCameraSource(createLocalSource(), 'local')}
                    >Local camera</button>
                    <button
                        type="button"
                        class:active={sourceKind === 'paired_phone'}
                        aria-pressed={sourceKind === 'paired_phone'}
                        disabled={!cameraPairing || !cameraEnabled || pairingState === 'creating' || pairingState === 'rejoining' || pairingState === 'disconnecting' || operationUnresolved || disabled || paused}
                        onclick={() => void connectPairedPhone()}
                    >{controllerBootstrap ? 'Reconnect phone' : 'Connect phone'}</button>
                </div>
                {#if !cameraPairing && pairingUnavailableReason}
                    <p class="settings-source" role="status">
                        {pairingUnavailableReason === 'omr_camera_remote_disabled'
                            ? 'Phone pairing is disabled on this deployment. An administrator must configure and verify the remote-camera service before enabling it. Local camera and image upload remain available.'
                            : 'The remote-camera protocol is unavailable for this workbench. Ask an administrator to complete its camera deployment.'}
                    </p>
                {/if}
                {#if cameraPairing}
                    <p class="settings-source">For a direct Wi-Fi or phone-hotspot trial, keep both devices on the same network. Some hotspots block local device traffic; capture waits for a moving phone preview.</p>
                {/if}
                    {#if pairingError}<p class="settings-empty" role="alert">{pairingError}</p>{/if}
                {#if sourceKind === 'local'}
                    <span class="settings-source">{cameraState === 'ready' ? 'Local camera preview' : 'Local camera not connected'}</span>
                    {#if cameraDevices.length > 0}
                        {#each cameraDevices as device, index (device.id)}
                            <button
                                type="button"
                                class="settings-option"
                                disabled={!device.selectable || disabled || paused || !cameraEnabled}
                                aria-pressed={selectedDeviceId === device.id}
                                onclick={() => void switchDevice(device.id)}
                            >
                                <span>{device.label || shortDeviceLabel(device, index)}</span>
                                {#if selectedDeviceId === device.id}<Check class="size-4 shrink-0" />{/if}
                            </button>
                        {/each}
                    {:else}
                        <p class="settings-empty">No local camera detected.</p>
                    {/if}
                    <button
                        type="button"
                        class="settings-refresh"
                        disabled={disabled || paused || !cameraEnabled}
                        onclick={() => void refreshCameraDevices()}
                    >Refresh local cameras</button>
                {:else}
                    <span class="settings-source">
                        {pairingState === 'paired'
                            ? 'Phone camera connected'
                            : pairingState === 'connecting'
                              ? 'Waiting for the phone camera…'
                            : pairingState === 'creating'
                              ? 'Creating a secure pairing…'
                              : pairingState === 'rejoining'
                                ? 'Replacing the phone connection…'
                                : pairingState === 'disconnecting'
                                  ? 'Disconnecting phone…'
                                  : 'Phone camera disconnected'}
                    </span>
                    {#if controllerBootstrap?.pairCode}
                        <div class="settings-option" data-camera-pair-code>
                            <span>Pair code <strong class="font-mono">{controllerBootstrap.pairCode}</strong></span>
                            <button type="button" class="settings-refresh" onclick={() => void copyPairCode()}>Copy code</button>
                        </div>
                    {/if}
                    {#if safeSensorUrl}
                        <a class="settings-option" href={safeSensorUrl} target="_blank" rel="noopener noreferrer">
                            <span>Open phone camera link</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    {/if}
                    {#if controllerBootstrap}
                        <div class="settings-group">
                            <button
                                type="button"
                                class="settings-option"
                                disabled={!cameraPairing || operationUnresolved || disabled || paused}
                                onclick={requestPairedPhoneRejoin}
                            ><span>Replace / reconnect phone</span><RefreshCw class="size-4 shrink-0" /></button>
                            {#if operationUnresolved}
                                <p class="settings-empty" role="status">Finish or reconcile the current capture before replacing this phone connection.</p>
                            {/if}
                            {#if rejoinConfirmationOpen}
                                <div class="settings-option flex-col items-start" role="group" aria-label="Confirm phone reconnection">
                                    <strong>Replace this phone connection?</strong>
                                    <span>A new one-use pair code will be issued. Accepted captures and the current page-swap hold remain on this desktop.</span>
                                    <div class="settings-choice-row w-full">
                                        <button type="button" disabled={operationUnresolved || !cameraPairing} onclick={() => void confirmPairedPhoneRejoin()}>Confirm reconnect</button>
                                        <button type="button" onclick={cancelPairedPhoneRejoin}>Keep current connection</button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}
                    {#if pairingNotice}<p class="settings-source" role="status">{pairingNotice}</p>{/if}
                    <button
                        type="button"
                        class="settings-refresh"
                        disabled={pairingState === 'disconnecting' || disabled}
                        onclick={() => void disconnectPairedPhone()}
                    >Disconnect phone</button>
                {/if}
                {#if sourceKind === 'local' && pairingError}<p class="settings-empty" role="alert">{pairingError}</p>{/if}
            </div>
            <div class="settings-group">
                <span class="settings-label">Batch capture</span>
                <div class="settings-choice-row" role="group" aria-label="Batch capture trigger">
                    <button
                        type="button"
                        class:active={batchTrigger === 'auto_steady'}
                        aria-pressed={batchTrigger === 'auto_steady'}
                        onclick={() => (batchTrigger = 'auto_steady')}
                    >Auto steady</button>
                    <button
                        type="button"
                        class:active={batchTrigger === 'manual_qr'}
                        aria-pressed={batchTrigger === 'manual_qr'}
                        onclick={() => (batchTrigger = 'manual_qr')}
                    >Manual / QR</button>
                </div>
                {#if mode === 'auto' || (mode === 'batch' && batchTrigger === 'auto_steady')}
                    <label class="settings-field">
                        <span>Steady delay</span>
                        <select
                            bind:value={steadyDelayMs}
                            aria-label="Steady delay"
                            onchange={(event) => setSteadyDelay(Number(event.currentTarget.value) as 150 | 300 | 500 | 800)}
                        >
                            <option value={150}>150 ms · Fast</option>
                            <option value={300}>300 ms · Standard</option>
                            <option value={500}>500 ms · Careful</option>
                            <option value={800}>800 ms · Deliberate</option>
                        </select>
                    </label>
                {/if}
            </div>
            <div class="settings-group">
                <span class="settings-label">Capture assistance</span>
                <label class="settings-field">
                    <span>Manual / QR countdown</span>
                    <select
                        value={countdownSeconds}
                        aria-label="Manual and QR countdown"
                        onchange={(event) => setCountdownSeconds(Number(event.currentTarget.value))}
                    >
                        <option value={0}>Off</option>
                        {#each Array.from({ length: 10 }, (_, index) => index + 1) as seconds (seconds)}
                            <option value={seconds}>{seconds} seconds</option>
                        {/each}
                    </select>
                </label>
                <button
                    type="button"
                    class="settings-toggle"
                    aria-pressed={applyCountdownToAuto}
                    onclick={() => (applyCountdownToAuto = !applyCountdownToAuto)}
                >
                    <span>Apply countdown to Auto</span>
                    <span>{applyCountdownToAuto ? 'On' : 'Off'}</span>
                </button>
                <button
                    type="button"
                    class="settings-toggle"
                    aria-pressed={voiceCuesEnabled}
                    onclick={() => setVoiceCues(!voiceCuesEnabled)}
                >
                    {#if voiceCuesEnabled}<Volume2 class="size-4" />{:else}<VolumeX class="size-4" />{/if}
                    <span>Voice cues</span>
                </button>
            </div>
            <div class="settings-group">
                <span class="settings-label">Command tools</span>
                <button
                    type="button"
                    class="settings-toggle"
                    aria-pressed={qrTriggerEnabled}
                    title={qrSupported ? 'Enable the in-frame QR command trigger' : 'QR command detection is unavailable for the selected source'}
                    disabled={!qrSupported || paused}
                    onclick={() => (qrTriggerEnabled = !qrTriggerEnabled)}
                >
                    <QrCode class="size-4" />
                    <span>QR command trigger</span>
                    <span>{qrTriggerEnabled ? 'On' : 'Off'}</span>
                </button>
                <p class="settings-source">{qrSupported ? 'Recognizes versioned NEXIAM command cards.' : 'The selected source has not reported a usable QR reader.'}</p>
                <button
                    type="button"
                    class="settings-option"
                    onclick={() => (commandCardsOpen = true)}
                ><span>Print command cards</span><QrCode class="size-4 shrink-0" /></button>
                {#each ['next_page', 'next_set', 'redo_last'] as action (action)}
                    {#if authorizedActions[action as OmrCaptureAction]}
                        <button
                            type="button"
                            class="settings-option"
                            disabled={captureActionBusy || !lifecycleState?.lastAccepted || (paused && !settingsOpen) || !visible}
                            onclick={() => void lifecycle?.requestAction(action as OmrCaptureAction)}
                        ><span>{action === 'next_page' ? 'Next page' : action === 'next_set' ? 'Next set' : 'Redo last page'}</span></button>
                        {#if lifecycleState?.actionResults[action as OmrCaptureAction]}
                            {@const result = lifecycleState.actionResults[action as OmrCaptureAction]}
                            <p class="settings-source" role={result?.supported === true && result.accepted ? 'status' : 'alert'}>
                                {result?.supported === 'unknown'
                                    ? 'Outcome is unknown. Reconcile this action before trying again.'
                                    : result?.supported === true && result.accepted
                                      ? 'Action accepted. Follow the authorized target shown by the workbench.'
                                      : `Action not completed (${result?.reason ?? 'unavailable'}). No target change is confirmed.`}
                            </p>
                        {/if}
                    {/if}
                {/each}
            </div>
            {#if settingsExtras}{@render settingsExtras()}{/if}
        </section>
    {/if}

    <div class="omr-workspace">
        <section class="capture-screen" aria-label="Camera capture">
            <div class="camera-stage" class:camera-stage-placeholder={cameraState !== 'ready'}>
                <!-- svelte-ignore a11y_media_has_caption -->
                <video
                    class="camera-feed"
                    class:camera-feed-hidden={cameraState === 'unavailable' || cameraState === 'idle'}
                    bind:this={videoEl}
                    autoplay
                    playsinline
                    muted
                    aria-label="Live camera preview"
                    onloadedmetadata={onVideoMetadata}
                ></video>
                {#if cameraState === 'ready'}
                    <div class="camera-stage-header" class:has-page-badge={Boolean(currentTarget)}>
                        {#if currentTarget}
                            <div class="page-badge" role="status">
                                Page {currentPage} of {pageCount}
                            </div>
                        {/if}
                        <div class="scan-hint">
                            <ScanLine size={14} />
                            <span>Align all four sheet corners inside the frame</span>
                        </div>
                    </div>
                    <div class="frame frame-tl"></div>
                    <div class="frame frame-tr"></div>
                    <div class="frame frame-bl"></div>
                    <div class="frame frame-br"></div>
                    <OmrViewfinderHud
                        result={viewfinderResult}
                        available={viewfinderAvailable}
                        manual={mode === 'manual' || (mode === 'batch' && batchTrigger === 'manual_qr')}
                        {videoWidth}
                        {videoHeight}
                        lockProgress={lifecycleState?.lockProgress ?? 0}
                        captureBlockedReason={recoveryStorageIssue ??
                            (disabled ? disabledReason : null) ??
                            (lifecycleState &&
                            (Boolean(lifecycleState.sessionRecovery) ||
                                !['armed', 'countdown'].includes(
                                    lifecycleState.phase,
                                ))
                                ? (lifecycleState.message &&
                                  (cameraState !== 'ready' ||
                                      lifecycleState.message !==
                                          'Waiting for a fresh camera frame.')
                                      ? lifecycleState.message
                                      : (lifecycleState.awaitingSheetSwap
                                          ? 'Remove the captured sheet before the next capture.'
                                          : 'Capture is paused. Follow the capture status before trying again.'))
                                : null)}
                    />
                    {#if lifecycleState?.awaitingSheetSwap}
                        <div class="sheet-swap-banner">
                            <span>Remove the previous sheet. Use an authorized Next page/set action in Settings, or retake a selected page from Queue.</span>
                        </div>
                    {/if}
                    <p class="camera-caption">
                        {cameraCaption ??
                            (sourceKind === 'paired_phone'
                                ? 'Live phone camera preview · align the full answer sheet inside the frame.'
                                : 'Local camera preview · align the full answer sheet inside the frame.')}
                    </p>
                {:else if cameraState === 'requesting'}
                    <div class="camera-message">
                        <RefreshCw class="spin" size={24} />
                        <strong>{sourceKind === 'paired_phone' ? 'Connecting phone camera…' : 'Requesting camera access…'}</strong>
                        <small>{sourceKind === 'paired_phone' ? 'Waiting for a fresh decoded phone frame.' : 'Allow camera access to capture in place.'}</small>
                    </div>
                {:else}
                    <div class="camera-message">
                        <Camera size={32} class="muted-stage-icon" />
                        <strong>
                            {cameraError?.toLowerCase().includes('permission')
                                ? 'Camera permission unavailable'
                                : sourceKind === 'paired_phone'
                                  ? 'Phone camera disconnected'
                                  : 'Camera device unavailable'}
                        </strong>
                        <small>
                            {(disabled ? disabledReason : cameraError) ??
                                'Connect a camera or use file upload instead.'}
                        </small>
                        {#if allowUpload}
                            <button
                                class="primary-button stage-upload-btn"
                                type="button"
                                onclick={triggerFileUpload}
                                disabled={!uploadAvailable || fileBatchBusy}
                            >
                                <Upload size={15} /> Upload image file
                            </button>
                        {/if}
                    </div>
                {/if}
                {#if lifecycleState?.phase === 'countdown'}
                    <div class="capture-preview-status" role="status" aria-live="assertive">
                        <strong>Capture in {lifecycleState.countdownRemaining ?? countdownSeconds}</strong>
                        <button type="button" onclick={cancelCapture}>Cancel countdown</button>
                    </div>
                {:else if lifecycleState?.phase === 'awaiting_card_removal'}
                    <div class="capture-preview-status" role="status" aria-live="assertive">
                        <strong>Remove the command card</strong>
                        <span>Capture will wait for the card to leave the frame and a fresh sheet view.</span>
                        <button type="button" onclick={cancelCapture}>Cancel command</button>
                    </div>
                {:else if lifecycleState?.phase === 'frame_awaiting_review'}
                    <div class="capture-preview-status" role="status" aria-live="polite">
                        <strong>Review the original frame</strong>
                        <span>Choose the crop or keep the original before submission.</span>
                    </div>
                {:else if lifecycleState?.phase === 'pending' || lifecycleState?.phase === 'reconciling'}
                    <div class="capture-preview-status" role="status" aria-live="polite">
                        <strong>{lifecycleState.message ?? 'Checking the previous capture outcome.'}</strong>
                        {#if lifecycleState.sessionRecovery === 'reconcile_required'}
                            <a class="underline underline-offset-2" href="/login" target="_blank" rel="noopener noreferrer">Sign in in another tab</a>
                        {/if}
                        <div class="flex items-center gap-3 mt-2">
                            <button type="button" onclick={() => void retryAndReconcile()}>Check outcome</button>
                        </div>
                    </div>
                {:else if lifecycleState?.phase === 'rejected'}
                    <div class="capture-preview-status" role="alert" aria-live="assertive">
                        <strong>{lifecycleState.message ?? 'Capture was not accepted.'}</strong>
                        {#if lifecycleState.sessionRecovery === 'submit_rejected'}
                            <a class="underline underline-offset-2" href="/login" target="_blank" rel="noopener noreferrer">Sign in in another tab</a>
                            <span>After sign-in, reopen this scanner from the staff workspace and retake.</span>
                        {:else if lifecycleState.sessionRecovery === 'storage_unavailable'}
                            <button type="button" onclick={() => window.location.reload()}>Reload after enabling session storage</button>
                        {/if}
                        {#if lifecycleState.retryAvailable && !failedFiles.some((failed) => failed.operation.operationId === lifecycleState?.operation?.operationId)}
                            <button type="button" onclick={() => void retryAndReconcile()}>Retry same original</button>
                        {/if}
                        <button type="button" class="underline underline-offset-2 hover:opacity-80 text-xs" disabled={fileBatchBusy || lifecycleState.sessionRecovery === 'storage_unavailable' || lifecycleState.sessionRecovery === 'reconcile_required'} onclick={resetPreflight}>Retake with new photo</button>
                    </div>
                {:else if lifecycleState?.phase === 'accepted'}
                    <div class="capture-preview-status" role="status" aria-live="polite">
                        <strong>{lifecycleState.message ?? 'Capture accepted by the workbench.'}</strong>
                    </div>
                {:else if lifecycleState?.message}
                    <div class="capture-preview-status" role="status" aria-live="polite">
                        <span>{lifecycleState.message}</span>
                    </div>
                {/if}
                {#if lifecycleState?.phase === 'frame_awaiting_review' && cropPreviewError}
                    <div class="capture-preview-status" role="alert" aria-live="assertive">
                        <strong>{cropPreviewError}</strong>
                        {#if lifecycleState.frame?.kind === 'staged'}
                            <button type="button" onclick={() => void retryCropPreview()}>Retry private preview</button>
                        {/if}
                        <button type="button" onclick={cancelCropCheck}>Cancel capture</button>
                    </div>
                {/if}

                {#if preflightState === 'checking'}
                    <div class="preflight-panel" role="status" aria-live="polite">
                        <span class="preflight-badge neutral"><RefreshCw class="spin" size={12} /> Checking</span>
                        <p class="preflight-detail">Server registration check in progress — hold the sheet steady.</p>
                    </div>
                {:else if preflightState === 'validated'}
                    <div class="preflight-panel validated" role="status" aria-live="polite">
                        <div class="preflight-head">
                            <span class="preflight-badge ok"><Check size={12} /> Validated</span>
                            <small class="preflight-calibration">Calibration: Unqualified</small>
                        </div>
                        {#if preflight?.gates}
                            <div class="gate-chips">
                                {#each preflightGateOrder as key (key)}
                                    {@const gate = preflight.gates[key]}
                                    {#if gate}
                                        {@const passed = gate.passed === true || gate.ok === true || gate.status === 'passed'}
                                        <span class={`gate-chip ${passed ? 'gate-pass' : 'gate-fail'}`}>
                                            {preflightGateLabels[key] ?? key}: {passed ? 'Pass' : 'Check'}
                                        </span>
                                    {/if}
                                {/each}
                            </div>
                        {/if}
                        {#if preflight?.normalized_dimensions}
                            <p class="preflight-meta">
                                Normalized {preflight.normalized_dimensions.width}×{preflight.normalized_dimensions.height}px — uploading…
                            </p>
                        {:else}
                            <p class="preflight-meta">Uploading the checked frame…</p>
                        {/if}
                    </div>
                {:else if preflightState === 'rejected'}
                    <div class="preflight-panel rejected" role="alert" aria-live="polite">
                        <div class="preflight-head">
                            <span class="preflight-badge bad"><X size={12} /> Rejected</span>
                            {#if preflight?.code}<small class="preflight-code">{preflight.code}</small>{/if}
                        </div>
                        <p class="preflight-detail">{preflight?.detail ?? 'The sheet did not pass the marker registration check.'}</p>
                        {#if preflight?.orientation_hint}<p class="preflight-hint">{preflight.orientation_hint}</p>{/if}
                        <button type="button" class="text-button preflight-retake" onclick={resetPreflight}>
                            <RefreshCw size={14} /> Retake / replace
                        </button>
                    </div>
                {:else if preflightState === 'error'}
                    <div class="preflight-panel rejected" role="alert" aria-live="polite">
                        <div class="preflight-head">
                            <span class="preflight-badge bad"><X size={12} /> Check failed</span>
                        </div>
                        <p class="preflight-detail">{preflightError}</p>
                        <button type="button" class="text-button preflight-retake" onclick={resetPreflight}>
                            <RefreshCw size={14} /> Retry
                        </button>
                    </div>
                {/if}
            </div>

            <div class="capture-controls">
                {#if onOpenGallery}
                    <button
                        class="control-side"
                        type="button"
                        aria-label="Open scans gallery"
                        onclick={onOpenGallery}
                    ><LayoutGrid size={22} /><span>Queue</span></button>
                {:else}
                    <span class="control-spacer" aria-hidden="true"></span>
                {/if}
                <button
                    class="shutter"
                    type="button"
                    aria-label={pageCount > 1
                        ? `Capture page ${currentPage} of ${pageCount}`
                        : 'Capture sheet'}
                    onclick={() => lifecycle?.trigger('shutter')}
                    disabled={!captureAvailable ||
                        submissionBusy ||
                        fileBatchBusy ||
                        lifecycleState?.phase === 'frame_awaiting_review' ||
                        preflightState === 'checking'}
                ><span><Camera size={25} /></span></button>
                <div class="mode-toggle" role="group" aria-label="Capture mode">
                    {#each ['manual', 'auto', 'batch'] as captureMode (captureMode)}
                        <button
                            class:active={mode === captureMode}
                            type="button"
                            aria-pressed={mode === captureMode}
                            onclick={() => setMode(captureMode as OmrCameraMode)}
                        >{captureMode === 'manual' ? 'Manual' : captureMode === 'auto' ? 'Auto' : 'Batch'}</button>
                    {/each}
                </div>
            </div>

            <div class="camera-footer">
                {#if pageCount > 1}
                    <span class="page-step" role="status">Set page {currentPage} of {pageCount}</span>
                {/if}
                {#if authorizedActions['redo_last'] && lifecycleState?.lastAccepted}
                    <button
                        type="button"
                        class="text-button text-amber-600 dark:text-amber-400 font-semibold"
                        disabled={captureActionBusy || (paused && !settingsOpen) || !visible}
                        onclick={() => void lifecycle?.requestAction('redo_last')}
                        title="Discard and retake the last captured page"
                    ><RotateCcw size={14} /> Redo last page</button>
                {/if}
                {#if cameraState === 'unavailable' || cameraState === 'idle'}
                    <button
                        type="button"
                        class="text-button"
                        onclick={() => void retryCameraConnection()}
                        disabled={!cameraEnabled || paused || disabled || !visible || sourceKind !== 'local'}
                    ><RefreshCw size={14} /> Retry camera connection</button>
                {:else if allowUpload}
                    <button
                        type="button"
                        class="text-button"
                        onclick={triggerFileUpload}
                        disabled={!uploadAvailable || fileBatchBusy}
                    ><FileImage size={14} />{singleUpload ? 'Upload one page image' : 'File batch upload'}</button>
                {/if}
            </div>

            {#if fileBatchBusy || fileBatchError || failedFiles.length > 0 || deferredFiles.length > 0}
                <section class="upload-manifest" aria-label="Batch upload manifest" aria-live="polite">
                    <div class="manifest-heading">
                        <div>
                            <strong>{fileBatchBusy ? 'Batch upload' : 'Upload results'}</strong>
                            <span>{fileBatchBusy ? `Uploading ${fileBatchCurrent} of ${fileBatchTotal} files · sequential backpressure` : `${fileBatchTotal} files · sequential backpressure`}</span>
                        </div>
                        {#if fileBatchBusy}<RefreshCw class="spin" size={14} />{/if}
                    </div>
                    {#if fileBatchBusy}
                        <progress
                            class="manifest-progress"
                            value={fileBatchCompleted}
                            max={Math.max(fileBatchTotal, 1)}
                            aria-label="Files uploaded"
                        ></progress>
                    {/if}
                    {#if fileBatchError}<p class="manifest-error">{fileBatchError}</p>{/if}
                    {#if failedFiles.length > 0}
                        <div class="manifest-list">
                            {#each failedFiles as failedFile (failedFile.id)}
                                <div class="manifest-row">
                                    <div class="manifest-copy">
                                        <strong>{failedFile.file.name}</strong>
                                        <span>Page {failedFile.operation.target.pageNo} · rejected</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-button"
                                        onclick={() => void retryFailedFile(failedFile)}
                                        disabled={fileBatchBusy || Boolean(lifecycleState?.sessionRecovery)}
                                    >Retry</button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                    {#if deferredFiles.length > 0}
                        <div class="manifest-list" aria-label="Deferred uploads">
                            {#each deferredFiles as deferred (deferred.id)}
                                <div class="manifest-row">
                                    <div class="manifest-copy">
                                        <strong>{deferred.file.name}</strong>
                                        <span>Waiting for the next capture target</span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </section>
            {/if}
        </section>
    </div>

    {#if allowUpload}
        <input
            bind:this={fileInputEl}
            type="file"
            accept={singleUpload
                ? 'image/jpeg,image/png,image/webp'
                : 'image/jpeg,image/png,image/webp,application/pdf,image/tiff'}
            multiple={!singleUpload}
            disabled={!uploadAvailable || fileBatchBusy}
            class="file-input"
            onchange={handleFileChange}
        />
    {/if}
    {#if lifecycleState?.phase === 'frame_awaiting_review' && cropPreviewFile}
        <OmrCropCheckModal
            file={cropPreviewFile}
            initialQuad={lifecycleState.crop?.quad ?? null}
            disabled={!uploadAvailable}
            onUse={(quad) => void finishCropCheck(quad, 'auto_document_quad')}
            onKeep={() => void finishCropCheck(null, 'keep_original')}
            onCancel={cancelCropCheck}
        />
    {/if}
    <OmrCommandCardsDialog bind:open={commandCardsOpen} />
</div>

<style>
    .omr-app {
        --omr-bg: var(--background);
        --omr-surface: var(--card);
        --omr-surface-solid: var(--card-solid, var(--card));
        --omr-text: var(--foreground);
        --omr-muted: var(--muted-foreground);
        --omr-border: var(--border);
        --omr-primary: var(--primary);
        --omr-primary-soft: color-mix(in oklab, var(--primary) 12%, transparent);
        --omr-stage: oklch(0.27 0.04 148);
        --omr-stage-deep: oklch(0.2 0.035 148);
        --omr-stage-text: oklch(0.94 0.02 145);
        min-height: 0;
        width: 100%;
        height: 100%;
        max-width: 100%;
        margin: 0;
        display: flex;
        flex-direction: column;
        background: var(--omr-bg);
        color: var(--omr-text);
        font-family: var(--font-sans);
        overflow: hidden;
    }
    .omr-app,
    .omr-app * {
        box-sizing: border-box;
    }
    .omr-workspace,
    .capture-screen {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        width: 100%;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }
    .camera-stage {
        position: relative;
        display: flex;
        isolation: isolate;
        flex: 1 1 0;
        align-items: center;
        justify-content: center;
        min-height: 0;
        overflow: hidden;
        background: radial-gradient(circle at 50% 43%, var(--omr-stage), var(--omr-stage-deep) 72%);
    }
    .camera-stage-placeholder {
        flex-direction: column;
        justify-content: safe center;
        gap: 12px;
        padding: 12px;
        overflow-y: auto;
    }
    .camera-stage-placeholder .camera-message {
        flex-shrink: 0;
        max-height: none;
        overflow-y: visible;
        padding: 0;
    }
    .camera-stage-placeholder .capture-preview-status {
        position: static;
        flex-shrink: 0;
        max-width: 100%;
        transform: none;
    }
    .camera-feed {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .camera-feed-hidden { visibility: hidden; }
    .capture-preview-status {
        position: absolute;
        z-index: 30;
        bottom: 24%;
        left: 50%;
        display: flex;
        max-width: min(92%, 34rem);
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        transform: translateX(-50%);
        border: 1px solid color-mix(in oklab, var(--omr-primary) 38%, white 8%);
        border-radius: 10px;
        background: color-mix(in oklab, var(--omr-stage-deep) 94%, transparent);
        color: var(--omr-stage-text);
        font-size: 12px;
        line-height: 1.4;
        text-align: center;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.22);
    }
    .capture-preview-status button {
        padding: 5px 9px;
        border: 1px solid color-mix(in oklab, var(--omr-stage-text) 35%, transparent);
        border-radius: var(--radius-md);
        background: transparent;
        color: var(--omr-stage-text);
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
    }
    .capture-preview-status button:hover:not(:disabled) {
        border-color: var(--omr-primary);
        color: var(--omr-primary);
    }
    .camera-stage-header {
        position: absolute;
        top: 12px;
        left: 12px;
        right: 12px;
        z-index: 12;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        pointer-events: none;
    }
    .camera-stage-header.has-page-badge {
        justify-content: space-between;
    }
    .camera-stage-header > * {
        pointer-events: auto;
    }
    .scan-hint {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--omr-stage-text);
        font: 10px var(--font-mono);
        letter-spacing: 0.08em;
        text-transform: uppercase;
        opacity: 0.85;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .scan-hint span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    @media (max-width: 540px) {
        .camera-stage-header.has-page-badge .scan-hint {
            display: none;
        }
    }
    .frame {
        position: absolute;
        z-index: 2;
        width: 31px;
        height: 31px;
        border-color: color-mix(in oklab, var(--primary) 70%, white);
        opacity: 0.9;
        pointer-events: none;
    }
    .frame-tl { top: 12%; left: 8%; border-top: 2px solid; border-left: 2px solid; }
    .frame-tr { top: 12%; right: 8%; border-top: 2px solid; border-right: 2px solid; }
    .frame-bl { bottom: 12%; left: 8%; border-bottom: 2px solid; border-left: 2px solid; }
    .frame-br { right: 8%; bottom: 12%; border-right: 2px solid; border-bottom: 2px solid; }
    .camera-caption {
        position: absolute;
        z-index: 11;
        top: 50px;
        left: 12px;
        right: 12px;
        max-width: 38rem;
        padding: 5px 8px;
        border-radius: 6px;
        background: color-mix(in oklab, var(--omr-stage-deep) 82%, transparent);
        color: var(--omr-stage-text);
        font-size: 11px;
        line-height: 1.35;
    }
    .camera-message {
        display: flex;
        max-height: 100%;
        min-height: 0;
        overflow-y: auto;
        flex-shrink: 1;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 25px;
        color: var(--omr-stage-text);
        text-align: center;
    }
    .camera-message strong { color: white; font-size: 16px; font-weight: 600; }
    .camera-message small { max-width: 30rem; color: color-mix(in oklab, var(--omr-stage-text) 76%, transparent); font-size: 12px; line-height: 1.45; }
    .muted-stage-icon { color: color-mix(in oklab, var(--omr-stage-text) 60%, transparent); }
    .stage-upload-btn { margin-top: 4px; }
    .primary-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 40px;
        padding: 0 15px;
        border: 1px solid var(--omr-primary);
        border-radius: var(--radius-md);
        background: var(--omr-primary);
        color: var(--primary-foreground);
        font-size: 12px;
        font-weight: 650;
        cursor: pointer;
    }
    .primary-button:hover:not(:disabled) {
        filter: brightness(1.08);
    }
    .primary-button:disabled {
        cursor: not-allowed;
        opacity: 0.55;
    }
    .capture-controls {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 18px clamp(18px, 4vw, 42px) 11px;
        border-top: 1px solid var(--omr-border);
        background: var(--omr-surface-solid);
    }
    .control-side {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        border: 0;
        background: transparent;
        color: var(--omr-muted);
        font-size: 11px;
        cursor: pointer;
    }
    .control-side:hover,
    .text-button:hover { color: var(--omr-primary); }
    .control-spacer { width: 42px; }
    .shutter {
        width: 68px;
        height: 68px;
        flex: 0 0 auto;
        padding: 5px;
        border: 4px solid color-mix(in oklab, var(--omr-stage-text) 90%, transparent);
        border-radius: 50%;
        background: transparent;
        cursor: pointer;
    }
    .shutter span {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--omr-primary);
        color: var(--primary-foreground);
    }
    .shutter:disabled { opacity: 0.45; cursor: not-allowed; }
    .mode-toggle {
        display: flex;
        padding: 3px;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-md);
        background: var(--omr-primary-soft);
    }
    .mode-toggle button {
        padding: 7px 9px;
        border: 0;
        border-radius: calc(var(--radius-md) - 2px);
        background: transparent;
        color: var(--omr-muted);
        font: 11px var(--font-mono);
        cursor: pointer;
    }
    .mode-toggle button.active { background: var(--omr-primary); color: var(--primary-foreground); }
    .camera-footer {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        gap: 19px;
        min-height: 44px;
        padding: 0 16px 18px;
        background: var(--omr-surface-solid);
    }
    .text-button {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 0;
        background: transparent;
        color: var(--omr-muted);
        font-size: 11px;
        cursor: pointer;
    }
    .text-button:disabled,
    .settings-option:disabled,
    .settings-refresh:disabled,
    .settings-toggle:disabled { cursor: not-allowed; opacity: 0.55; }
    .page-badge {
        position: relative;
        flex-shrink: 0;
        padding: 5px 10px;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-full, 999px);
        background: color-mix(in srgb, var(--omr-bg) 82%, transparent);
        color: var(--omr-primary);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.02em;
        backdrop-filter: blur(6px);
    }
    .page-step { color: var(--omr-primary); font-size: 12px; font-weight: 600; }
    .preflight-panel {
        position: absolute;
        z-index: 12;
        bottom: 21%;
        left: 50%;
        display: flex;
        flex-direction: column;
        gap: 6px;
        width: min(92%, 400px);
        padding: 10px 12px;
        transform: translateX(-50%);
        border: 1px solid color-mix(in oklab, var(--omr-primary) 45%, white 12%);
        border-radius: 10px;
        background: color-mix(in oklab, var(--omr-stage-deep) 88%, transparent);
        color: var(--omr-stage-text);
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.22);
        backdrop-filter: blur(3px);
    }
    .preflight-panel.validated { border-color: color-mix(in oklab, #65c18c 60%, white 10%); }
    .preflight-panel.rejected { border-color: color-mix(in oklab, #e05252 65%, white 8%); }
    .preflight-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .preflight-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
    .preflight-badge.ok { background: color-mix(in oklab, #65c18c 22%, transparent); color: #a7e3bc; }
    .preflight-badge.bad { background: color-mix(in oklab, #e05252 22%, transparent); color: #f2a8a8; }
    .preflight-badge.neutral { background: color-mix(in oklab, var(--omr-primary) 18%, transparent); color: var(--omr-stage-text); }
    .preflight-calibration, .preflight-code { color: color-mix(in oklab, var(--omr-stage-text) 78%, transparent); font-size: 10px; }
    .preflight-detail { color: var(--omr-stage-text); font-size: 12px; line-height: 1.45; }
    .preflight-hint { color: #f2c9a8; font-size: 11px; }
    .preflight-meta { color: color-mix(in oklab, var(--omr-stage-text) 80%, transparent); font-size: 11px; }
    .preflight-retake { align-self: flex-start; margin-top: 2px; }
    .gate-chips { display: flex; flex-wrap: wrap; gap: 5px; }
    .gate-chip { padding: 2px 8px; border: 1px solid; border-radius: 999px; font-size: 10px; font-weight: 600; letter-spacing: 0.02em; }
    .gate-chip.gate-pass { border-color: color-mix(in oklab, #65c18c 55%, transparent); background: color-mix(in oklab, #65c18c 14%, transparent); color: #a7e3bc; }
    .gate-chip.gate-fail { border-color: color-mix(in oklab, #e05252 60%, transparent); background: color-mix(in oklab, #e05252 14%, transparent); color: #f2a8a8; }
    .upload-manifest { flex: 0 1 auto; max-height: min(22vh, 180px); overflow-y: auto; padding: 10px clamp(16px, 3vw, 28px) 14px; border-top: 1px solid var(--omr-border); background: var(--omr-surface-solid); color: var(--omr-text); }
    .manifest-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 11px; }
    .manifest-heading > div { display: flex; flex-direction: column; gap: 2px; }
    .manifest-heading span, .manifest-copy span { color: var(--omr-muted); }
    .manifest-progress { display: block; width: 100%; height: 6px; margin-top: 8px; accent-color: var(--omr-primary); }
    .manifest-error { margin-top: 7px; color: #b45309; font-size: 11px; }
    .manifest-list { display: grid; gap: 7px; margin-top: 8px; }
    .manifest-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .manifest-copy { display: flex; min-width: 0; flex-direction: column; gap: 2px; font-size: 11px; }
    .manifest-copy strong, .manifest-copy span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .capture-settings {
        position: fixed;
        z-index: 50;
        top: 4rem;
        right: 0.75rem;
        display: grid;
        gap: 12px;
        width: min(22rem, calc(100vw - 1.5rem));
        max-height: min(70vh, 520px);
        overflow-y: auto;
        padding: 1rem;
        border: 1px solid var(--omr-border);
        border-radius: var(--radius-xl);
        background: var(--omr-surface-solid);
        color: var(--omr-text);
        box-shadow: var(--shadow-lg);
    }
    .sheet-swap-banner {
        position: absolute;
        bottom: 4.5rem;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 24px);
        max-width: 28rem;
        z-index: 25;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        background: rgba(15, 23, 42, 0.9);
        border: 1px solid var(--omr-primary, #10b981);
        border-radius: 12px;
        color: white;
        font-size: 12px;
        font-weight: 500;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
        white-space: normal;
        line-height: 1.4;
    }
    .settings-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .settings-heading h2 { margin: 0; font-size: 14px; font-weight: 650; }
    .settings-close, .settings-refresh { border: 0; background: transparent; color: var(--omr-muted); font-size: 11px; cursor: pointer; }
    .settings-close { padding: 5px 8px; border-radius: var(--radius-md); }
    .settings-close:hover, .settings-refresh:hover { color: var(--omr-primary); }
    .settings-group { display: grid; gap: 7px; padding-top: 10px; border-top: 1px solid var(--omr-border); }
    .settings-group:first-of-type { padding-top: 0; border-top: 0; }
    .settings-label { font-size: 11px; font-weight: 600; }
    .settings-source, .settings-empty { color: var(--omr-muted); font-size: 11px; }
    .settings-option { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: transparent; color: var(--omr-text); text-align: left; font-size: 11px; cursor: pointer; }
    .settings-option:hover:not(:disabled), .settings-option[aria-pressed="true"] { background: var(--omr-primary-soft); color: var(--omr-primary); }
    .settings-option span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .settings-refresh { justify-self: start; padding: 4px 0; }
    .settings-choice-row { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
    .settings-choice-row button { padding: 7px 8px; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: transparent; color: var(--omr-muted); font-size: 10px; cursor: pointer; }
    .settings-choice-row button.active { border-color: var(--omr-primary); background: var(--omr-primary-soft); color: var(--omr-primary); font-weight: 650; }
    .settings-field { display: grid; gap: 5px; color: var(--omr-muted); font-size: 11px; }
    .settings-field select { width: 100%; padding: 8px 9px; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: var(--omr-surface); color: var(--omr-text); font: 12px var(--font-sans); }
    .settings-toggle { display: flex; align-items: center; gap: 8px; padding: 7px 9px; border: 0; border-radius: var(--radius-md); background: transparent; color: var(--omr-text); text-align: left; font-size: 11px; cursor: pointer; }
    .settings-toggle:hover:not(:disabled) { background: var(--omr-primary-soft); color: var(--omr-primary); }
    .file-input { display: none; }
    @media (max-width: 640px) {
        .camera-message { gap: 6px; padding: 8px; }
        .camera-message > :global(svg) { display: none; }
        .camera-message strong { font-size: 13px; }
        .camera-message small { font-size: 11px; }
        .capture-controls { gap: 10px; padding: 18px 15px 11px; }
        .shutter { width: 62px; height: 62px; }
        .shutter span { width: 44px; height: 44px; }
        .mode-toggle button { padding: 7px 6px; font-size: 10px; }
        .camera-footer { flex-wrap: wrap; gap: 10px; }
        .camera-caption { max-width: none; }
        .capture-settings { top: 3.5rem; }
    }
</style>
