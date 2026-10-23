import type { OmrCaptureTargetContext } from './omrCameraAcquisition';
import type { ViewfinderResult } from './omrViewfinderWorker';
import type {
    OmrCameraSource,
    OmrCameraSourceConfig,
    OmrCameraSourceEvent,
    OmrCaptureAcceptance,
    OmrCaptureAction,
    OmrCaptureActionResult,
    OmrCaptureCrop,
    OmrCaptureCue,
    OmrCaptureLifecyclePhase,
    OmrCaptureOperation,
    OmrCaptureOperationReference,
    OmrCaptureTrigger,
    OmrCapturedFrame,
    OmrCaptureMode,
    OmrCaptureBatchTrigger,
} from './omrCaptureProtocol';
import { formatOmrWorkbenchError, isOmrWorkbenchValidationError } from './omrWorkbenchClient';

export type OmrLifecycleAuthorization = Readonly<{
    authorizationId: string;
    targetKey: string;
    acceptedTargetKey: string;
}>;

export type OmrCaptureLifecycleContext = Readonly<{
    workbenchId: string;
    target: OmrCaptureTargetContext | null;
    sourceGeneration: number;
    configRevision: number;
    registrationMode: 'fiducial_anchored' | 'document_bound';
    pageCount: number;
    cameraEnabled: boolean;
    visible: boolean;
    paused: boolean;
    ready: boolean;
    physicalSwapObserved: boolean;
    authorizedReplacementOfCaptureId?: string | number | null;
    cropReviewRequired: boolean;
    settingsActionsAvailable?: boolean;
    authorizedActions?: Partial<Record<OmrCaptureAction, OmrLifecycleAuthorization>>;
}>;

export type OmrCaptureLifecycleSettings = Readonly<{
    mode: OmrCaptureMode;
    batchTrigger: OmrCaptureBatchTrigger;
    countdownSeconds: number;
    applyCountdownToAuto: boolean;
    steadyDelayMs: 150 | 300 | 500 | 800;
    qrTriggerEnabled: boolean;
    cropPolicy: 'auto_document_quad' | 'keep_original';
}>;

export type OmrAcceptedCaptureReference = Readonly<{
    operation: Pick<OmrCaptureOperation, 'operationId' | 'target'>;
    receipt: OmrCaptureAcceptance;
}>;

export type OmrCaptureLifecycleState = Readonly<{
    phase: OmrCaptureLifecyclePhase;
    context: OmrCaptureLifecycleContext;
    operation: OmrCaptureOperation | null;
    recoveryOperation: OmrCaptureOperationReference | null;
    frame: OmrCapturedFrame | null;
    acceptance: OmrCaptureAcceptance | null;
    crop: OmrCaptureCrop | null;
    countdownRemaining: number | null;
    awaitingSheetSwap: boolean;
    cardRemovalSequence: number | null;
    quality: ViewfinderResult | null;
    qualityFresh: boolean;
    lockProgress: number;
    lastQualitySequence: number;
    lastAccepted: OmrAcceptedCaptureReference | null;
    sessionRecovery: 'submit_rejected' | 'reconcile_required' | 'storage_unavailable' | null;
    message: string | null;
    stage: string | null;
    recovery: string | null;
    retryAvailable: boolean;
    actionResults: Partial<Record<OmrCaptureAction, OmrCaptureActionResult>>;
    consumedActionAuthorizationIds: Partial<Record<OmrCaptureAction, string>>;
}>;

export type OmrCaptureTimerHandle = ReturnType<typeof globalThis.setTimeout>;

export interface OmrCaptureClock {
    now(): number;
    setTimeout(callback: () => void, delayMs: number): OmrCaptureTimerHandle;
    clearTimeout(handle: OmrCaptureTimerHandle): void;
}

export type OmrCaptureAdapter = Readonly<{
    submit(frame: OmrCapturedFrame, operation: OmrCaptureOperation, crop: OmrCaptureCrop): Promise<OmrCaptureAcceptance>;
    reconcile(operation: OmrCaptureOperationReference): Promise<OmrCaptureAcceptance>;
    requestAction?(
        action: OmrCaptureAction,
        authorization: OmrLifecycleAuthorization,
        lastAccepted: OmrCaptureLifecycleState['lastAccepted'],
    ): Promise<OmrCaptureActionResult>;
}>;

export type OmrCaptureLifecycleOptions = Readonly<{
    source: OmrCameraSource;
    adapter: OmrCaptureAdapter;
    context: OmrCaptureLifecycleContext;
    initialAccepted?: OmrAcceptedCaptureReference | null;
    settings?: Partial<OmrCaptureLifecycleSettings>;
    initialRecovery?: OmrCaptureOperationReference | null;
    initialRecoveryError?: string | null;
    clock?: OmrCaptureClock;
    uuid?: () => string;
    onState?: (state: OmrCaptureLifecycleState) => void;
    onCue?: (cue: OmrCaptureCue) => void;
}>;

export type OmrCaptureLifecycle = Readonly<{
    getState(): OmrCaptureLifecycleState;
    subscribe(listener: (state: OmrCaptureLifecycleState) => void): () => void;
    updateContext(context: OmrCaptureLifecycleContext): void;
    updateSettings(settings: Partial<OmrCaptureLifecycleSettings>): void;
    trigger(trigger: OmrCaptureTrigger, commandSequence?: number): boolean;
    acceptFrame(frame: OmrCapturedFrame, trigger?: OmrCaptureTrigger): boolean;
    cancel(): void;
    pause(reason?: string): void;
    resume(): void;
    dismissRecovery(): void;
    retry(): Promise<void>;
    confirmCrop(crop: OmrCaptureCrop): Promise<void>;
    requestAction(action: OmrCaptureAction): Promise<OmrCaptureActionResult>;
    dispose(): void;
}>;

const defaultSettings: OmrCaptureLifecycleSettings = {
    mode: 'manual',
    batchTrigger: 'manual_qr',
    countdownSeconds: 3,
    applyCountdownToAuto: false,
    steadyDelayMs: 300,
    qrTriggerEnabled: false,
    cropPolicy: 'auto_document_quad',
};

const QUALITY_FRESHNESS_MS = 500;

const defaultClock: OmrCaptureClock = {
    now: () => globalThis.performance?.now?.() ?? Date.now(),
    setTimeout: (callback, delayMs) => globalThis.setTimeout(callback, delayMs),
    clearTimeout: (handle) => globalThis.clearTimeout(handle),
};

function normalizeSettings(settings: Partial<OmrCaptureLifecycleSettings>): OmrCaptureLifecycleSettings {
    const next = { ...defaultSettings, ...settings };
    if (!Number.isInteger(next.countdownSeconds) || next.countdownSeconds < 0 || next.countdownSeconds > 10) {
        next.countdownSeconds = defaultSettings.countdownSeconds;
    }
    return next;
}

function makeSourceConfig(
    context: OmrCaptureLifecycleContext,
    settings: OmrCaptureLifecycleSettings,
    acceptedTarget: OmrCaptureTargetContext | null,
): OmrCameraSourceConfig | null {
    const target = context.target ?? acceptedTarget;
    if (!target || target.workbenchId !== context.workbenchId) return null;
    return {
        target,
        sourceGeneration: context.sourceGeneration,
        configRevision: context.configRevision,
        registrationMode: context.registrationMode,
        pageCount: context.pageCount,
        mode: settings.mode,
        batchTrigger: settings.batchTrigger,
        steadyDelayMs: settings.steadyDelayMs,
        qrTriggerEnabled: settings.qrTriggerEnabled,
        paused: context.paused || !context.visible,
    };
}

function sameContext(a: OmrCaptureLifecycleContext, b: OmrCaptureLifecycleContext): boolean {
    return a.workbenchId === b.workbenchId && a.target?.targetKey === b.target?.targetKey &&
        a.sourceGeneration === b.sourceGeneration && a.configRevision === b.configRevision;
}

function matchingReceipt(receipt: OmrCaptureAcceptance, operation: OmrAcceptedCaptureReference['operation']): boolean {
    const hasCaptureEvidence = typeof receipt.captureId === 'number'
        ? Number.isSafeInteger(receipt.captureId) && receipt.captureId > 0
        : typeof receipt.captureId === 'string' && receipt.captureId.trim().length > 0;
    return receipt.operationId === operation.operationId &&
        receipt.targetKey === operation.target.targetKey &&
        receipt.pageNo === operation.target.pageNo &&
        (receipt.status !== 'accepted' || hasCaptureEvidence);
}

function validFrame(frame: OmrCapturedFrame, operation: OmrCaptureOperation, allowUnknownRawDimensions = false): boolean {
    const metadataValid = frame.targetKey === operation.target.targetKey &&
        frame.sourceGeneration === operation.sourceGeneration && frame.inputHash.length > 0;
    if (!metadataValid) return false;
    const dimensionsValid = (width: number | null, height: number | null, orientation: number | null): boolean => {
        if (width === null || height === null || orientation === null) return allowUnknownRawDimensions && frame.kind === 'raw' &&
            (width === null || Number.isSafeInteger(width) && width > 0) &&
            (height === null || Number.isSafeInteger(height) && height > 0) &&
            (orientation === null || Number.isFinite(orientation));
        return Number.isSafeInteger(width) && width > 0 && Number.isSafeInteger(height) && height > 0 && Number.isFinite(orientation);
    };
    if (!dimensionsValid(frame.width, frame.height, frame.orientation)) return false;
    return frame.kind === 'raw'
        ? frame.file.size > 0
        : frame.frameId.length > 0 && frame.previewUrl.length > 0 && frame.commandId.length > 0 && frame.pairingId.length > 0;
}

function allQualityGatesPass(result: ViewfinderResult | null): boolean {
    return Boolean(result && result.quad && result.gates.in_bounds && result.gates.angle_ok && result.gates.steady_ok && result.gates.lighting_ok);
}
function sameSourceConfig(left: OmrCameraSourceConfig | null, right: OmrCameraSourceConfig | null): boolean {
    return left === right || Boolean(left && right &&
        left.target.targetKey === right.target.targetKey &&
        left.sourceGeneration === right.sourceGeneration &&
        left.configRevision === right.configRevision &&
        left.registrationMode === right.registrationMode &&
        left.pageCount === right.pageCount &&
        left.mode === right.mode &&
        left.batchTrigger === right.batchTrigger &&
        left.steadyDelayMs === right.steadyDelayMs &&
        left.qrTriggerEnabled === right.qrTriggerEnabled &&
        left.paused === right.paused);
}

function sessionExpiredError(error: unknown): boolean {
    if (error === null || typeof error !== 'object') return false;
    const status = 'status' in error && typeof error.status === 'number' ? error.status : null;
    return status === 401 || status === 419;
}

function isAutomatic(trigger: OmrCaptureTrigger): boolean {
    return trigger === 'steady';
}

function captureCrop(frame: OmrCapturedFrame, context: OmrCaptureLifecycleContext, settings: OmrCaptureLifecycleSettings): OmrCaptureCrop {
    return {
        policy: context.registrationMode === 'fiducial_anchored' ? 'auto_document_quad' : settings.cropPolicy,
        quad: frame.clientQuad ?? null,
    };
}

function makeInitialState(
    context: OmrCaptureLifecycleContext,
    initialAccepted?: OmrAcceptedCaptureReference | null,
    initialRecovery?: OmrCaptureOperationReference | null,
    initialRecoveryError?: string | null,
): OmrCaptureLifecycleState {
    const recovery = initialRecovery?.target.workbenchId === context.workbenchId ? initialRecovery : null;
    const recoveryError = recovery === null ? initialRecoveryError?.trim() || null : null;
    const accepted = recovery === null && initialAccepted?.receipt.status === 'accepted' &&
        initialAccepted.operation.target.workbenchId === context.workbenchId &&
        matchingReceipt(initialAccepted.receipt, initialAccepted.operation)
        ? initialAccepted : null;
    const authorizedRetakeTarget = accepted !== null &&
        context.authorizedReplacementOfCaptureId != null &&
        context.target !== null &&
        context.target.targetKey !== accepted.operation.target.targetKey;
    return {
        phase: recovery ? 'reconciling' : recoveryError ? 'rejected' : context.cameraEnabled && context.ready && !context.paused && context.visible && context.target ? accepted && !authorizedRetakeTarget ? 'held' : 'armed' : 'unavailable',
        context,
        operation: null,
        recoveryOperation: recovery,
        frame: null,
        acceptance: null,
        crop: null,
        awaitingSheetSwap: accepted !== null && !authorizedRetakeTarget,
        cardRemovalSequence: null,
        quality: null,
        qualityFresh: false,
        lockProgress: 0,
        lastQualitySequence: -1,
        lastAccepted: accepted,
        message: recovery
            ? 'A previous capture may have committed. Check this operation before capturing again.'
            : recoveryError,
        stage: recovery || recoveryError ? 'recovery' : null,
        recovery: recovery
            ? 'Reconcile the saved capture operation before continuing.'
            : recoveryError,
        sessionRecovery: recovery
            ? 'reconcile_required'
            : recoveryError ? 'storage_unavailable' : null,
        retryAvailable: false,
        actionResults: {},
        consumedActionAuthorizationIds: {},
    };
}

export function createOmrCaptureLifecycle(options: OmrCaptureLifecycleOptions): OmrCaptureLifecycle {
    const clock = options.clock ?? defaultClock;
    const uuid = options.uuid ?? (() => globalThis.crypto.randomUUID());
    let settings = normalizeSettings(options.settings ?? {});
    const listeners = new Set<(state: OmrCaptureLifecycleState) => void>();
    let sourceAbort: AbortController | null = null;
    let state = makeInitialState(options.context, options.initialAccepted, options.initialRecovery, options.initialRecoveryError);
    const consumedReplacementCaptureIds = new Set<string>();
    if (state.context.authorizedReplacementOfCaptureId != null) {
        consumedReplacementCaptureIds.add(String(state.context.authorizedReplacementOfCaptureId));
    }
    let countdownTimer: OmrCaptureTimerHandle | null = null;
    let qualityExpiryTimer: OmrCaptureTimerHandle | null = null;
    let countdownStartedAt = 0;
    let autoSteadyTimer: OmrCaptureTimerHandle | null = null;
    let autoSteadyStartedAt: number | null = null;
    let lastQualityObservedAt = Number.NEGATIVE_INFINITY;
    let qualityReady = false;
    let acquisitionAbort: AbortController | null = null;
    let busy = false;
    let busyOperationId: string | null = null;
    let disposed = false;
    let sourceConnected = false;
    let sourceAttached = false;
    let operationSequence = -1;
    let sourceConnecting = false;
    let sourceConfigSequence = 0;
    let configuredRevision: number | null = null;

    const publish = (next: OmrCaptureLifecycleState): void => {
        state = next;
        options.onState?.(state);
        for (const listener of listeners) listener(state);
    };

    const cue = (name: OmrCaptureCue['name'], operationId: string | null = state.operation?.operationId ?? null, tick?: number): void => {
        options.onCue?.({ cueId: `${operationId ?? 'lifecycle'}:${name}${tick === undefined ? '' : `:${tick}`}`, operationId, name, ...(tick === undefined ? {} : { tick }) });
    };

    const clearCountdown = (): void => {
        if (countdownTimer === null) return;
        clock.clearTimeout(countdownTimer);
        countdownTimer = null;
    };
    const clearQualityExpiry = (): void => {
        if (qualityExpiryTimer === null) return;
        clock.clearTimeout(qualityExpiryTimer);
        qualityExpiryTimer = null;
    };
    const clearAutoSteady = (): void => {
        if (autoSteadyTimer !== null) clock.clearTimeout(autoSteadyTimer);
        autoSteadyTimer = null;
        autoSteadyStartedAt = null;
    };

    const detachedFileOperation = (): boolean => state.operation?.sourceId === 'file-upload' &&
        ['frame_awaiting_review', 'submitting', 'reconciling', 'pending', 'rejected'].includes(state.phase);

    const currentContextAllows = (operation: OmrCaptureOperation): boolean =>
        !disposed && state.context.ready && state.context.visible && !state.context.paused &&
        state.context.target?.targetKey === operation.target.targetKey &&
        state.context.workbenchId === operation.target.workbenchId &&
        (operation.sourceId === 'file-upload' ||
            state.context.sourceGeneration === operation.sourceGeneration && state.context.configRevision === operation.configRevision);
    const sessionRecoveryHold = (): boolean => state.sessionRecovery !== null;
    const unresolvedCapture = (): boolean =>
        ['submitting', 'reconciling', 'pending'].includes(state.phase) ||
        state.phase === 'rejected' && state.retryAvailable ||
        state.sessionRecovery !== null;
    const preservedCapturePhase = (): OmrCaptureLifecyclePhase =>
        state.sessionRecovery === 'reconcile_required' ? 'reconciling'
            : state.sessionRecovery === 'submit_rejected' || state.sessionRecovery === 'storage_unavailable' ? 'rejected'
                : state.phase;


    const qualityIsFresh = (afterSequence = -1): boolean =>
        qualityReady && state.lastQualitySequence > afterSequence && clock.now() - lastQualityObservedAt <= QUALITY_FRESHNESS_MS;

    const sourceEvent = (event: OmrCameraSourceEvent): void => {
        if (disposed || !state.context.cameraEnabled) return;
        if (event.type === 'configured') {
            const current = state.context;
            const config = event.config;
            const expectedTarget = current.target ?? state.lastAccepted?.operation.target;
            if (!expectedTarget || config.target.workbenchId !== current.workbenchId ||
                config.target.targetKey !== expectedTarget.targetKey) {
                configuredRevision = null;
                publish({ ...state, phase: sessionRecoveryHold() ? preservedCapturePhase() : detachedFileOperation() ? state.phase : 'unavailable',
                    message: sessionRecoveryHold() ? state.message : 'The camera configuration does not match the authorized target.' });
                return;
            }
            if (config.sourceGeneration < current.sourceGeneration || config.configRevision < current.configRevision) return;
            const nextContext: OmrCaptureLifecycleContext = {
                ...current,
                target: current.target === null ? null : config.target,
                sourceGeneration: config.sourceGeneration,
                configRevision: config.configRevision,
                registrationMode: config.registrationMode,
                pageCount: config.pageCount,
            };
            if (!detachedFileOperation() && !sameContext(current, nextContext) && ['countdown', 'awaiting_card_removal', 'acquiring', 'frame_awaiting_review'].includes(state.phase)) lifecycle.cancel();
            configuredRevision = config.configRevision;
            clearQualityExpiry();
            clearAutoSteady();
            qualityReady = false;
            lastQualityObservedAt = Number.NEGATIVE_INFINITY;
            const unresolved = unresolvedCapture() || detachedFileOperation();
            publish({
                ...state,
                context: nextContext,
                phase: unresolved ? preservedCapturePhase() :
                    !sourceConnected || !current.ready || !current.visible || current.paused || config.paused
                        ? 'unavailable' : state.awaitingSheetSwap ? 'held' : 'armed',
                quality: null,
                qualityFresh: false,
                lockProgress: 0,
                lastQualitySequence: -1,
            });
            return;
        }
        if (event.type === 'status') {
            sourceConnected = event.status === 'ready';
            if (!sourceConnected) {
                clearQualityExpiry();
                qualityReady = false;
                lastQualityObservedAt = Number.NEGATIVE_INFINITY;
                if (event.status !== 'connecting') {
                    configuredRevision = null;
                    sourceConfigSequence += 1;
                    if (!detachedFileOperation() &&
                        ['countdown', 'awaiting_card_removal', 'acquiring', 'frame_awaiting_review'].includes(state.phase)) {
                        lifecycle.cancel();
                    }
                }
                const unresolved = unresolvedCapture() || detachedFileOperation();
                if (!unresolved) {
                    publish({ ...state, phase: 'unavailable', quality: null, qualityFresh: false, lockProgress: 0, message: event.message ?? 'Camera source is not ready.' });
                } else {
                    publish({ ...state, phase: preservedCapturePhase(), quality: null, qualityFresh: false, lockProgress: 0 });
                }
                cue('disconnected');
            } else if (configuredRevision === state.context.configRevision &&
                state.context.ready && state.context.visible && !state.context.paused && !busy &&
                !unresolvedCapture() && !detachedFileOperation()) {
                publish({ ...state, phase: state.awaitingSheetSwap ? 'held' : 'armed', message: null });
            } else if (sourceConnected && state.message === 'Waiting for a fresh camera frame.') {
                publish({ ...state, message: null });
            }
            return;
        }
        if (event.type === 'quality') {
            if (event.sourceGeneration !== state.context.sourceGeneration || event.configRevision !== state.context.configRevision ||
                configuredRevision !== state.context.configRevision || event.targetKey !== (state.context.target ?? state.lastAccepted?.operation.target)?.targetKey ||
                event.sequence <= state.lastQualitySequence) return;
            const age = event.relativeAgeMs ?? 0;
            const observedAt = clock.now() - age;
            const valid = event.ready && Number.isFinite(age) && age >= 0 && age <= QUALITY_FRESHNESS_MS && allQualityGatesPass(event.result);
            if (!valid || observedAt - lastQualityObservedAt > 250 || observedAt < lastQualityObservedAt) clearAutoSteady();
            clearQualityExpiry();
            lastQualityObservedAt = observedAt;
            qualityReady = valid;
            const automaticMode = settings.mode === 'auto' || settings.mode === 'batch' && settings.batchTrigger === 'auto_steady';
            const accumulating = valid && automaticMode && state.context.ready && state.context.target &&
                state.phase === 'armed' && !state.awaitingSheetSwap;
            if (accumulating && autoSteadyStartedAt === null) autoSteadyStartedAt = observedAt;
            publish({
                ...state,
                quality: event.result,
                qualityFresh: valid,
                lockProgress: accumulating && autoSteadyStartedAt !== null
                    ? Math.min(1, Math.max(0, (observedAt - autoSteadyStartedAt) / settings.steadyDelayMs)) : 0,
                lastQualitySequence: event.sequence,
                message: state.message === 'Waiting for a fresh camera frame.' ? null : state.message,
            });
            const timely = Number.isFinite(age) && age >= 0 && age <= QUALITY_FRESHNESS_MS;
            if (timely && event.result) {
                qualityExpiryTimer = clock.setTimeout(() => {
                    qualityExpiryTimer = null;
                    if (event.sequence !== state.lastQualitySequence) return;
                    qualityReady = false;
                    clearAutoSteady();
                    publish({ ...state, quality: null, qualityFresh: false, lockProgress: 0 });
                    if (state.phase === 'countdown' && (state.operation?.trigger === 'steady' || state.operation?.trigger === 'qr_capture')) {
                        lifecycle.cancel();
                    }
                }, Math.max(0, QUALITY_FRESHNESS_MS - age));
            } else {
                clearAutoSteady();
            }
            if (!valid && state.phase === 'countdown' && (state.operation?.trigger === 'steady' || state.operation?.trigger === 'qr_capture')) {
                lifecycle.cancel();
                publish({ ...state, phase: state.awaitingSheetSwap ? 'held' : 'armed', message: 'Framing changed; trigger capture again.' });
            } else if (valid && state.phase === 'awaiting_card_removal' && state.operation &&
                state.cardRemovalSequence !== null && event.sequence > state.cardRemovalSequence) {
                const operation = state.operation;
                publish({ ...state, phase: 'armed', cardRemovalSequence: null, message: null });
                startCountdown(operation, settings.countdownSeconds);
            }
            if (accumulating && autoSteadyTimer === null && autoSteadyStartedAt !== null) {
                autoSteadyTimer = clock.setTimeout(() => {
                    autoSteadyTimer = null;
                    if (state.phase === 'armed' && !state.awaitingSheetSwap && qualityIsFresh() &&
                        clock.now() - lastQualityObservedAt <= 250 &&
                        configuredRevision === state.context.configRevision) {
                        lifecycle.trigger('steady');
                    } else {
                        clearAutoSteady();
                        publish({ ...state, lockProgress: 0 });
                    }
                }, Math.max(0, settings.steadyDelayMs - (clock.now() - autoSteadyStartedAt)));
            }
            return;
        }
        if (event.type === 'qr_intent') {
            if (event.targetKey !== (state.context.target ?? state.lastAccepted?.operation.target)?.targetKey || event.sourceGeneration !== state.context.sourceGeneration ||
                event.configRevision !== state.context.configRevision || configuredRevision !== state.context.configRevision) return;
            if (event.command === 'CAPTURE') {
                if (settings.qrTriggerEnabled) lifecycle.trigger('qr_capture', event.sequence);
            } else {
                void lifecycle.requestAction(event.command === 'NEXT_PAGE' ? 'next_page' : event.command === 'NEXT_SET' ? 'next_set' : 'redo_last');
            }
            return;
        }
        if (event.type === 'qr_card_removed') {
            if (state.phase !== 'awaiting_card_removal' || event.targetKey !== state.operation?.target.targetKey ||
                event.sourceGeneration !== state.operation.sourceGeneration || event.configRevision !== state.operation.configRevision ||
                event.sequence <= operationSequence) return;
            lastQualityObservedAt = Number.NEGATIVE_INFINITY;
            qualityReady = false;
            clearQualityExpiry();
            clearAutoSteady();
            publish({ ...state, qualityFresh: false, cardRemovalSequence: event.sequence, message: 'Command card removed; frame the answer sheet.' });
            return;
        }
        if (event.type === 'capture_request' && event.targetKey === state.context.target?.targetKey &&
            event.sourceGeneration === state.context.sourceGeneration && event.configRevision === state.context.configRevision &&
            configuredRevision === state.context.configRevision) lifecycle.trigger(event.trigger);
    };


    const configureSource = (): void => {
        const config = makeSourceConfig(state.context, settings, state.lastAccepted?.operation.target ?? null);
        if (!config || disposed || !state.context.cameraEnabled) return;
        const sequence = ++sourceConfigSequence;
        configuredRevision = null;
        void options.source.configure(config).then(() => {
            if (disposed || sequence !== sourceConfigSequence ||
                config.sourceGeneration !== state.context.sourceGeneration || config.configRevision !== state.context.configRevision ||
                options.source.kind !== 'local') return;
            configuredRevision = config.configRevision;
            if (sourceConnected && !unresolvedCapture() && !detachedFileOperation() && state.context.ready && state.context.visible && !state.context.paused) {
                publish({ ...state, phase: state.awaitingSheetSwap ? 'held' : 'armed', message: null });
            } else if (sourceConnected && state.message === 'Waiting for a fresh camera frame.') {
                publish({ ...state, message: null });
            }
        }).catch(() => {
            if (!disposed && sequence === sourceConfigSequence) {
                const unresolved = unresolvedCapture() || detachedFileOperation();
                publish({ ...state, phase: unresolved ? preservedCapturePhase() : 'unavailable',
                    ...(!unresolved ? { message: 'Camera configuration was not acknowledged.' } : {}) });
            }
        });
    };

    const connectSource = (): void => {
        if (disposed || !state.context.cameraEnabled || sourceAttached || sourceConnecting) return;
        const controller = new AbortController();
        sourceAbort = controller;
        sourceConnecting = true;
        void options.source.connect(sourceEvent, controller.signal).then(() => {
            if (disposed || controller.signal.aborted) return;
            sourceConnecting = false;
            sourceAttached = true;
            configureSource();
        }).catch(() => {
            if (disposed || controller.signal.aborted) return;
            sourceConnecting = false;
            sourceAttached = false;
            sourceConnected = false;
            const unresolved = unresolvedCapture() || detachedFileOperation();
            publish({ ...state, phase: unresolved ? preservedCapturePhase() : 'unavailable',
                ...(!unresolved ? { message: 'Camera source connection failed.' } : {}) });
        });
    };

    const disconnectSource = (): void => {
        sourceAbort?.abort();
        const wasAttached = sourceAttached || sourceConnecting;
        sourceAbort = null;
        sourceConnecting = false;
        clearQualityExpiry();
        qualityReady = false;
        lastQualityObservedAt = Number.NEGATIVE_INFINITY;
        sourceAttached = false;
        if (!wasAttached) return;
        sourceConnected = false;
        options.source.disconnect();
    };

    const finalizeReceipt = (receipt: OmrCaptureAcceptance, operation: OmrCaptureOperationReference, frame: OmrCapturedFrame | null, crop: OmrCaptureCrop | null): void => {
        const restoredRecovery = state.recoveryOperation?.operationId === operation.operationId;
        if (!matchingReceipt(receipt, operation)) {
            publish({ ...state, phase: 'reconciling', operation: restoredRecovery ? null : operation as OmrCaptureOperation,
                recoveryOperation: restoredRecovery ? operation : state.recoveryOperation, frame, crop, acceptance: null,
                message: 'Capture outcome did not match this operation; reconcile before capturing again.', retryAvailable: false });
            cue('checking_previous_capture', operation.operationId);
            return;
        }
        const currentOperation = restoredRecovery ? null : operation as OmrCaptureOperation;
        state = { ...state, sessionRecovery: null, recoveryOperation: null };
        if (receipt.status === 'accepted') {
            const feedback = [
                receipt.stage ? `Stage: ${receipt.stage}.` : null,
                receipt.recovery,
            ].filter((part): part is string => typeof part === 'string' && part.trim() !== '');
            publish({ ...state, phase: 'accepted', operation: currentOperation, frame, crop, acceptance: receipt,
                lastAccepted: { operation, receipt }, awaitingSheetSwap: true,
                message: [receipt.message ?? 'Capture accepted.', ...feedback].join(' '),
                stage: receipt.stage ?? null, recovery: receipt.recovery ?? null, retryAvailable: false });
            cue(receipt.stage || receipt.recovery ? 'capture_attention' : 'accepted', operation.operationId);
        } else if (receipt.status === 'pending') {
            publish({ ...state, phase: 'pending', operation: currentOperation, frame, crop, acceptance: receipt,
                message: receipt.message ?? 'Capture is still processing.', stage: 'processing', recovery: null, retryAvailable: false });
            cue('processing', operation.operationId);
        } else {
            const message = receipt.message ?? 'Capture was not accepted. Check the status for the reason.';
            const feedback = [
                receipt.stage ? `Stage: ${receipt.stage}.` : null,
                receipt.recovery,
            ].filter((part): part is string => typeof part === 'string' && part.trim() !== '');
            publish({ ...state, phase: 'rejected', operation: currentOperation, frame, crop, acceptance: receipt,
                message: [message, ...feedback].join(' '), stage: receipt.stage ?? null,
                recovery: receipt.recovery ?? null, retryAvailable: true });
            cue('rejected', operation.operationId);
        }
    };

    const reconcile = async (
        operation: OmrCaptureOperationReference,
        frame: OmrCapturedFrame | null,
        crop: OmrCaptureCrop | null,
        submissionError?: unknown,
    ): Promise<void> => {
        if (busy && busyOperationId !== operation.operationId) return;
        const currentTargetBelongsToWorkbench = state.context.target === null ||
            state.context.target.workbenchId === state.context.workbenchId;
        const currentWorkbenchOwnsOperation = operation.target.workbenchId === state.context.workbenchId &&
            currentTargetBelongsToWorkbench;
        const activeOperation = currentWorkbenchOwnsOperation &&
            state.operation !== null &&
            state.operation.operationId === operation.operationId &&
            state.operation.idempotencyKey === operation.idempotencyKey &&
            state.operation.target.workbenchId === operation.target.workbenchId &&
            state.operation.target.targetKey === operation.target.targetKey;
        const restoredRecovery = currentWorkbenchOwnsOperation &&
            state.recoveryOperation !== null &&
            state.recoveryOperation.operationId === operation.operationId &&
            state.recoveryOperation.idempotencyKey === operation.idempotencyKey &&
            state.recoveryOperation.target.workbenchId === operation.target.workbenchId &&
            state.recoveryOperation.target.targetKey === operation.target.targetKey &&
            frame === null &&
            crop === null &&
            state.sessionRecovery === 'reconcile_required';
        if (!activeOperation && !restoredRecovery) {
            if (!disposed && (state.operation?.operationId === operation.operationId || state.recoveryOperation?.operationId === operation.operationId)) {
                publish({ ...state, phase: 'reconciling', operation: restoredRecovery ? null : state.operation,
                    recoveryOperation: restoredRecovery ? operation : state.recoveryOperation, frame, crop,
                    message: 'This exact operation is not owned by the current workbench context. Restore its original authorized workbench before checking its outcome.', retryAvailable: false });
            }
            return;
        }
        const ownsLock = !busy;
        if (ownsLock) {
            busy = true;
            busyOperationId = operation.operationId;
        }
        const submissionFeedback = submissionError === undefined
            ? null
            : formatOmrWorkbenchError(submissionError);
        const submissionMessage = submissionFeedback
            ? [
                submissionFeedback.message,
                submissionFeedback.stage ? `Stage: ${submissionFeedback.stage}.` : null,
                submissionFeedback.recovery,
                'Outcome is unconfirmed. Check this exact operation before any retry.',
            ].filter((part): part is string => typeof part === 'string' && part.trim() !== '').join(' ')
            : 'Checking the previous capture outcome.';
        publish({ ...state, phase: 'reconciling', operation: restoredRecovery ? null : operation as OmrCaptureOperation,
            recoveryOperation: restoredRecovery ? operation : state.recoveryOperation,
            frame, crop, message: submissionMessage, retryAvailable: false });
        cue('checking_previous_capture', operation.operationId);
        try {
            if (disposed || state.operation?.operationId !== operation.operationId && state.recoveryOperation?.operationId !== operation.operationId) return;
            const receipt = await options.adapter.reconcile(operation);
            if (disposed || state.operation?.operationId !== operation.operationId && state.recoveryOperation?.operationId !== operation.operationId) return;
            if (receipt.status === 'pending' && matchingReceipt(receipt, operation)) {
                const unresolvedRequest = receipt.stage === 'recovery';
                publish({
                    ...state,
                    phase: 'pending',
                    acceptance: receipt,
                    message: receipt.message ?? (unresolvedRequest
                        ? 'Capture outcome remains unconfirmed.'
                        : 'Capture is still processing.'),
                    stage: receipt.stage ?? 'processing',
                    recovery: receipt.recovery ?? null,
                });
                cue(unresolvedRequest ? 'checking_previous_capture' : 'processing', operation.operationId);
                return;
            }
            finalizeReceipt(receipt, operation, frame, crop);
        } catch (error: unknown) {
            if (!disposed && (state.operation?.operationId === operation.operationId || state.recoveryOperation?.operationId === operation.operationId)) {
                const expired = sessionExpiredError(error);
                const feedback = formatOmrWorkbenchError(submissionError ?? error);
                const message = expired
                    ? 'Your staff session expired while checking this capture. Sign in in another tab, then reopen this scanner from the staff workspace and Check outcome. The original capture remains unresolved; do not retake it.'
                    : submissionError === undefined && state.sessionRecovery === 'reconcile_required'
                        ? `${feedback.message}${feedback.stage ? ` Stage: ${feedback.stage}.` : ''}${feedback.recovery ? ` ${feedback.recovery}` : ''} The original capture remains unconfirmed; do not retake it until its outcome is resolved.`
                        : submissionError === undefined
                            ? 'Capture outcome is unknown. Reconcile before retrying.'
                            : `${feedback.message}${feedback.stage ? ` Stage: ${feedback.stage}.` : ''}${feedback.recovery ? ` ${feedback.recovery}` : ''} Outcome remains unconfirmed. Check this exact operation before any retry.`;
                publish({ ...state, phase: 'reconciling', operation: restoredRecovery ? null : operation as OmrCaptureOperation,
                    recoveryOperation: restoredRecovery ? operation : state.recoveryOperation, frame, crop,
                    sessionRecovery: expired ? 'reconcile_required' : state.sessionRecovery,
                    message, stage: expired ? 'recovery' : feedback.stage ?? state.stage,
                    recovery: expired ? 'Sign in in a separate tab, then check this exact capture outcome.' : feedback.recovery ?? state.recovery,
                    retryAvailable: false });
                if (expired) cue('session_expired', operation.operationId);
            }
        } finally {
            if (ownsLock && busyOperationId === operation.operationId) {
                busy = false;
                busyOperationId = null;
            }
        }
    };

    const submit = async (frame: OmrCapturedFrame, operation: OmrCaptureOperation, crop: OmrCaptureCrop): Promise<void> => {
        if (busy) return;
        busy = true;
        busyOperationId = operation.operationId;
        publish({ ...state, phase: 'submitting', frame, operation, crop, acceptance: null, message: 'Submitting original capture.', retryAvailable: false });
        cue('upload_pending', operation.operationId);
        try {
            const receipt = await options.adapter.submit(frame, operation, crop);
            if (disposed || state.operation?.operationId !== operation.operationId) return;
            if (!matchingReceipt(receipt, operation) || receipt.status === 'pending') {
                await reconcile(operation, frame, crop);
                return;
            }
            finalizeReceipt(receipt, operation, frame, crop);
        } catch (error: unknown) {
            if (disposed || state.operation?.operationId !== operation.operationId) return;
            if (error !== null && typeof error === 'object' &&
                'code' in error && error.code === 'omr_capture_session_recovery_storage_unavailable') {
                publish({ ...state, phase: 'rejected', operation, frame, crop, sessionRecovery: 'storage_unavailable', stage: 'recovery',
                    recovery: 'Enable session storage and reload the scanner. No capture request was sent.',
                    message: error instanceof Error ? error.message : 'Session storage is unavailable. No capture request was sent.', retryAvailable: false });
                return;
            }
            if (isOmrWorkbenchValidationError(error)) {
                const feedback = formatOmrWorkbenchError(error);
                const ready = state.context.cameraEnabled && state.context.ready &&
                    state.context.visible && !state.context.paused && state.context.target !== null;
                publish({
                    ...state,
                    phase: ready ? state.awaitingSheetSwap ? 'held' : 'armed' : 'unavailable',
                    operation: null,
                    recoveryOperation: null,
                    frame: null,
                    crop: null,
                    acceptance: null,
                    sessionRecovery: null,
                    message: `${feedback.message} No capture was stored. Correct the input and submit a new capture.`,
                    stage: 'validation',
                    recovery: 'Correct the request and start a new capture.',
                    retryAvailable: false,
                });
                cue('rejected', operation.operationId);
                return;
            }
            if (sessionExpiredError(error)) {
                publish({ ...state, phase: 'rejected', operation, frame, crop, sessionRecovery: 'submit_rejected', stage: 'recovery',
                    recovery: 'Sign in in a separate tab, then reopen the scanner and retake the capture.',
                    message: 'Your staff session expired before this capture was submitted. Sign in in another tab, then reopen this scanner from the staff workspace and retake.', retryAvailable: false });
                cue('session_expired', operation.operationId);
                return;
            }
            await reconcile(operation, frame, crop, error);
        } finally {
            if (busyOperationId === operation.operationId) {
                busy = false;
                busyOperationId = null;
            }
        }
    };

    const acquire = async (operation: OmrCaptureOperation): Promise<void> => {
        const needsFreshQuality = operation.trigger === 'steady' || operation.trigger === 'qr_capture';
        if (busy || !sourceConnected || configuredRevision !== state.context.configRevision || !currentContextAllows(operation) || (needsFreshQuality && !qualityIsFresh())) {
            if (state.operation?.operationId === operation.operationId) lifecycle.cancel();
            return;
        }
        busy = true;
        busyOperationId = operation.operationId;
        const controller = new AbortController();
        acquisitionAbort = controller;
        publish({ ...state, phase: 'acquiring', operation, frame: null, crop: null, acceptance: null, countdownRemaining: null, message: null, retryAvailable: false });
        cue('acquiring', operation.operationId);
        try {
            const frame = await options.source.acquire(operation, controller.signal);
            if (!sourceConnected || configuredRevision !== operation.configRevision || !currentContextAllows(operation) ||
                state.operation?.operationId !== operation.operationId || !validFrame(frame, operation)) return;
            if (state.context.cropReviewRequired) {
                publish({ ...state, phase: 'frame_awaiting_review', frame, crop: captureCrop(frame, state.context, settings), message: null });
                return;
            }
            if (busyOperationId === operation.operationId) {
                busy = false;
                busyOperationId = null;
            }
            await submit(frame, operation, captureCrop(frame, state.context, settings));
        } catch {
            if (!disposed && state.operation?.operationId === operation.operationId && currentContextAllows(operation)) {
                publish({ ...state, phase: 'rejected', message: 'Camera could not acquire a still image.', retryAvailable: false });
                cue('rejected', operation.operationId);
            }
        } finally {
            if (busyOperationId === operation.operationId) {
                busy = false;
                busyOperationId = null;
            }
            if (acquisitionAbort === controller) acquisitionAbort = null;
        }
    };

    const startCountdown = (operation: OmrCaptureOperation, seconds: number): void => {
        if (seconds <= 0) {
            void acquire(operation);
            return;
        }
        countdownStartedAt = clock.now();
        publish({ ...state, phase: 'countdown', cardRemovalSequence: null, operation, frame: null, crop: null,
            acceptance: null, countdownRemaining: seconds, message: null, retryAvailable: false });
        cue('countdown', operation.operationId, seconds);
        const tick = (): void => {
            countdownTimer = null;
            const requiresFreshQuality = operation.trigger === 'steady' || operation.trigger === 'qr_capture';
            const qualityValid = !requiresFreshQuality || !qualityReady || clock.now() - lastQualityObservedAt <= QUALITY_FRESHNESS_MS;
            if (!currentContextAllows(operation) || state.operation?.operationId !== operation.operationId || !qualityValid ||
                (requiresFreshQuality && !qualityIsFresh(operation.trigger === 'qr_capture' ? state.lastQualitySequence - 1 : -1))) {
                lifecycle.cancel();
                return;
            }
            const elapsed = Math.floor((clock.now() - countdownStartedAt) / 1000);
            const remaining = Math.max(0, seconds - elapsed);
            if (remaining === 0) {
                publish({ ...state, phase: 'acquiring', countdownRemaining: 0 });
                void acquire(operation);
                return;
            }
            publish({ ...state, countdownRemaining: remaining });
            cue('countdown', operation.operationId, remaining);
            countdownTimer = clock.setTimeout(tick, Math.min(1000, remaining * 1000));
        };
        countdownTimer = clock.setTimeout(tick, Math.min(1000, seconds * 1000));
    };

    const lifecycle: OmrCaptureLifecycle = {
        getState: () => state,
        subscribe(listener) {
            listeners.add(listener);
            listener(state);
            return () => listeners.delete(listener);
        },
        updateContext(context) {
            if (disposed) return;
            const changedScope = context.workbenchId !== state.context.workbenchId ||
                context.target?.targetKey !== state.context.target?.targetKey;
            const invalidates = (detachedFileOperation() ? changedScope : !sameContext(state.context, context)) ||
                !context.ready || !context.visible || context.paused;
            if (invalidates && ['countdown', 'awaiting_card_removal', 'acquiring', 'frame_awaiting_review'].includes(state.phase)) lifecycle.cancel();
            const unresolved = unresolvedCapture() || detachedFileOperation() && !invalidates;
            const replacementCaptureId = context.authorizedReplacementOfCaptureId;
            const authorizedReplacement = replacementCaptureId != null &&
                context.target !== null &&
                context.target.targetKey !== state.context.target?.targetKey &&
                !consumedReplacementCaptureIds.has(String(replacementCaptureId));
            if (authorizedReplacement) {
                consumedReplacementCaptureIds.add(String(replacementCaptureId));
            }
            const awaitingSheetSwap = state.awaitingSheetSwap &&
                !context.physicalSwapObserved &&
                !authorizedReplacement;
            const phase: OmrCaptureLifecyclePhase = unresolved
                ? preservedCapturePhase()
                : !context.cameraEnabled || !context.ready || !context.visible || context.paused || !context.target
                    ? 'unavailable'
                    : ['unavailable', 'held', 'accepted'].includes(state.phase)
                        ? awaitingSheetSwap ? 'held' : 'armed'
                        : state.phase;
            const previousSourceConfig = makeSourceConfig(state.context, settings, state.lastAccepted?.operation.target ?? null);
            const nextSourceConfig = makeSourceConfig(context, settings, state.lastAccepted?.operation.target ?? null);
            const sourceConfigChanged = !sameSourceConfig(previousSourceConfig, nextSourceConfig);
            const physicalSwapTransition = context.physicalSwapObserved && !state.context.physicalSwapObserved;
            if (sourceConfigChanged || physicalSwapTransition) {
                clearQualityExpiry();
                clearAutoSteady();
                qualityReady = false;
                lastQualityObservedAt = Number.NEGATIVE_INFINITY;
                if (sourceConfigChanged) configuredRevision = null;
            }
            const qualityReset = sourceConfigChanged || physicalSwapTransition;
            publish({
                ...state,
                context,
                awaitingSheetSwap,
                phase,
                qualityFresh: qualityReset ? false : state.qualityFresh,
                quality: qualityReset ? null : state.quality,
                lockProgress: qualityReset ? 0 : state.lockProgress,
                lastQualitySequence: qualityReset ? -1 : state.lastQualitySequence,
                message: phase === 'armed' ? null : state.message,
            });
            if (context.cameraEnabled) {
                if (sourceAttached) {
                    if (sourceConfigChanged) configureSource();
                } else {
                    connectSource();
                }
            } else {
                disconnectSource();
            }
        },
        updateSettings(next) {
            if (disposed) return;
            const normalized = normalizeSettings({ ...settings, ...next });
            const changed = Object.keys(normalized).some((key) => normalized[key as keyof OmrCaptureLifecycleSettings] !== settings[key as keyof OmrCaptureLifecycleSettings]);
            if (!changed) return;
            clearAutoSteady();
            if (['countdown', 'awaiting_card_removal'].includes(state.phase)) lifecycle.cancel();
            const previousSourceConfig = makeSourceConfig(state.context, settings, state.lastAccepted?.operation.target ?? null);
            settings = normalized;
            const sourceConfigChanged = !sameSourceConfig(previousSourceConfig, makeSourceConfig(state.context, settings, state.lastAccepted?.operation.target ?? null));
            if (sourceConfigChanged) {
                clearQualityExpiry();
                clearAutoSteady();
                qualityReady = false;
                lastQualityObservedAt = Number.NEGATIVE_INFINITY;
                configuredRevision = null;
            }
            const unresolved = unresolvedCapture();
            const phase: OmrCaptureLifecyclePhase = unresolved
                ? preservedCapturePhase()
                : !state.context.cameraEnabled || !state.context.ready || !state.context.visible || state.context.paused || !state.context.target
                    ? 'unavailable'
                    : state.awaitingSheetSwap ? 'held' : 'armed';
            publish({
                ...state,
                phase,
                qualityFresh: sourceConfigChanged ? false : state.qualityFresh,
                lockProgress: sourceConfigChanged ? 0 : state.lockProgress,
                lastQualitySequence: sourceConfigChanged ? -1 : state.lastQualitySequence,
                countdownRemaining: null,
                message: sessionRecoveryHold() ? state.message : 'Settings changed; trigger capture again.',
            });
            if (state.context.cameraEnabled) {
                if (sourceAttached) {
                    if (sourceConfigChanged) configureSource();
                } else {
                    connectSource();
                }
            }
        },
        trigger(triggerName, commandSequence = -1) {
            const requiresQuality = triggerName === 'steady';
            if (disposed || busy || !state.context.target || !state.context.ready || !state.context.visible || state.context.paused ||
                state.sessionRecovery !== null || state.awaitingSheetSwap || (state.phase === 'rejected' && state.retryAvailable) ||
                ['reconciling', 'pending', 'submitting', 'countdown', 'awaiting_card_removal', 'acquiring', 'frame_awaiting_review'].includes(state.phase) ||
                !sourceConnected || configuredRevision !== state.context.configRevision || (requiresQuality && !qualityIsFresh()) ||
                (triggerName === 'qr_capture' && !settings.qrTriggerEnabled)) {
                publish({ ...state, message: state.awaitingSheetSwap ? 'Place a different physical sheet before capturing.' : 'Capture is not available in the current state.' });
                return false;
            }
            if (state.lastAccepted?.receipt.targetKey === state.context.target.targetKey) {
                publish({ ...state, phase: 'held', message: 'This target already has an accepted capture.' });
                return false;
            }
            const operationId = uuid();
            const operation: OmrCaptureOperation = {
                operationId,
                idempotencyKey: uuid(),
                target: state.context.target,
                sourceId: options.source.id,
                sourceGeneration: state.context.sourceGeneration,
                configRevision: state.context.configRevision,
                trigger: triggerName,
                mode: settings.mode,
            };
            operationSequence = commandSequence;
            publish({ ...state, phase: 'armed', operation, frame: null, crop: null, acceptance: null,
                cardRemovalSequence: null, countdownRemaining: null, message: null, retryAvailable: false });
            if (triggerName === 'qr_capture') {
                publish({ ...state, phase: 'awaiting_card_removal', message: 'Remove the command card before framing the answer sheet.' });
                cue('remove_command_card', operation.operationId);
                return true;
            }
            startCountdown(operation, isAutomatic(triggerName) && !settings.applyCountdownToAuto ? 0 : settings.countdownSeconds);
            return true;
        },
        acceptFrame(frame, triggerName = 'shutter') {
            const selectedFileForNewTarget = triggerName === 'shutter' &&
                frame.kind === 'raw' &&
                state.lastAccepted !== null &&
                state.context.target !== null &&
                frame.targetKey === state.context.target.targetKey &&
                state.context.target.targetKey !== state.lastAccepted.receipt.targetKey;
            if (disposed || busy || unresolvedCapture() || !['unavailable', 'armed', 'held', 'accepted'].includes(state.phase) ||
                (state.awaitingSheetSwap && !selectedFileForNewTarget) ||
                !state.context.target || !state.context.ready || !state.context.visible || state.context.paused ||
                state.lastAccepted?.receipt.targetKey === state.context.target.targetKey) return false;
            const operation: OmrCaptureOperation = {
                operationId: uuid(),
                idempotencyKey: uuid(),
                target: state.context.target,
                sourceId: frame.kind === 'raw' ? 'file-upload' : options.source.id,
                sourceGeneration: state.context.sourceGeneration,
                configRevision: state.context.configRevision,
                trigger: triggerName,
                mode: settings.mode,
            };
            if (!validFrame(frame, operation, true)) return false;
            operationSequence = -1;
            const crop = captureCrop(frame, state.context, settings);
            publish({ ...state, phase: state.context.cropReviewRequired ? 'frame_awaiting_review' : 'submitting',
                operation, frame, crop, acceptance: null, message: null, retryAvailable: false });
            if (state.context.cropReviewRequired) return true;
            void submit(frame, operation, crop);
            return true;
        },
        cancel() {
            if (disposed) return;
            clearCountdown();
            clearAutoSteady();
            if (sessionRecoveryHold() || ['submitting', 'reconciling', 'pending'].includes(state.phase)) return;
            acquisitionAbort?.abort();
            acquisitionAbort = null;
            busy = false;
            busyOperationId = null;
            operationSequence = -1;
            publish({ ...state, phase: state.context.cameraEnabled && state.context.ready && state.context.visible && !state.context.paused
                ? state.awaitingSheetSwap ? 'held' : 'armed' : 'unavailable', operation: null, frame: null, crop: null,
                acceptance: null, cardRemovalSequence: null, countdownRemaining: null, message: 'Capture cancelled.', retryAvailable: false });
        },
        pause(reason = 'Capture paused.') {
            if (disposed) return;
            if (['countdown', 'awaiting_card_removal', 'acquiring', 'frame_awaiting_review'].includes(state.phase)) lifecycle.cancel();
            const context = { ...state.context, paused: true };
            const unresolved = unresolvedCapture();
            publish({ ...state, context, phase: unresolved ? preservedCapturePhase() : 'unavailable',
                message: sessionRecoveryHold() ? state.message : reason });
            configureSource();
        },
        resume() {
            if (disposed) return;
            const context = { ...state.context, paused: false };
            const unresolved = unresolvedCapture();
            const phase: OmrCaptureLifecyclePhase = unresolved
                ? preservedCapturePhase()
                : context.cameraEnabled && context.ready && context.visible && context.target
                    ? state.awaitingSheetSwap ? 'held' : 'armed' : 'unavailable';
            publish({ ...state, context, phase, operation: unresolved ? state.operation : null,
                message: unresolved ? state.message : null });
            configureSource();
        },
        dismissRecovery() {
            if (disposed || state.sessionRecovery === 'reconcile_required' ||
                state.sessionRecovery === 'storage_unavailable' ||
                ['submitting', 'reconciling', 'pending'].includes(state.phase)) return;
            busy = false;
            busyOperationId = null;
            state = {
                ...state,
                sessionRecovery: null,
                recoveryOperation: null,
                operation: null,
                frame: null,
                crop: null,
                acceptance: null,
                message: null,
                stage: null,
                recovery: null,
            };
            const phase: OmrCaptureLifecyclePhase = state.context.cameraEnabled && state.context.ready && state.context.visible && !state.context.paused
                ? state.awaitingSheetSwap ? 'held' : 'armed'
                : 'unavailable';
            publish({ ...state, phase, message: null, retryAvailable: false });
        },
        async retry() {
            if (disposed || busy || state.sessionRecovery === 'submit_rejected' || state.sessionRecovery === 'storage_unavailable') return;
            const operation = state.operation ?? state.recoveryOperation;
            if (!operation) return;
            const { frame, crop } = state;
            if (['reconciling', 'pending'].includes(state.phase)) {
                await reconcile(operation, frame, crop);
                return;
            }
            if (state.phase === 'rejected' && state.operation && frame && crop && currentContextAllows(state.operation)) await submit(frame, state.operation, crop);
        },
        async confirmCrop(crop) {
            if (disposed || state.phase !== 'frame_awaiting_review' || !state.frame || !state.operation || !currentContextAllows(state.operation)) return;
            if (state.context.registrationMode === 'fiducial_anchored' && crop.policy === 'keep_original') {
                publish({ ...state, message: 'Keep Original is not available for fiducial-anchored paper.' });
                return;
            }
            const frame = state.frame;
            const operation = state.operation;
            await submit(frame, operation, crop);
        },
        async requestAction(action) {
            const authorization = state.context.authorizedActions?.[action];
            if (!authorization) {
                const result: OmrCaptureActionResult = { supported: false, action, reason: action === 'redo_last' && !state.lastAccepted ? 'no_accepted_capture' : 'unauthorized' };
                publish({ ...state, actionResults: { ...state.actionResults, [action]: result } });
                return result;
            }
            if (state.consumedActionAuthorizationIds[action] === authorization.authorizationId) {
                return state.actionResults[action] ?? { supported: false, action, reason: 'busy' };
            }
            if (busy || unresolvedCapture() || ['submitting', 'reconciling', 'pending', 'countdown', 'awaiting_card_removal', 'acquiring', 'frame_awaiting_review'].includes(state.phase) ||
                !state.context.visible || (state.context.paused && !state.context.settingsActionsAvailable) || (action === 'next_page' && !state.context.ready)) {
                const result: OmrCaptureActionResult = { supported: false, action, reason: 'busy' };
                publish({ ...state, actionResults: { ...state.actionResults, [action]: result } });
                return result;
            }
            if (!state.lastAccepted || state.lastAccepted.operation.target.workbenchId !== state.context.workbenchId ||
                authorization.acceptedTargetKey !== state.lastAccepted.receipt.targetKey) {
                const result: OmrCaptureActionResult = { supported: false, action, reason: 'unauthorized' };
                publish({ ...state, actionResults: { ...state.actionResults, [action]: result } });
                return result;
            }
            if (!options.adapter.requestAction) {
                const result: OmrCaptureActionResult = { supported: false, action, reason: 'unsupported' };
                publish({ ...state, actionResults: { ...state.actionResults, [action]: result } });
                return result;
            }
            const lastAccepted = state.lastAccepted;
            publish({
                ...state,
                consumedActionAuthorizationIds: { ...state.consumedActionAuthorizationIds, [action]: authorization.authorizationId },
            });
            let result: OmrCaptureActionResult;
            try {
                result = await options.adapter.requestAction(action, authorization, lastAccepted);
            } catch {
                result = { supported: 'unknown', action, reason: 'outcome_unknown' };
            }
            const releasesSwap = result.supported === true && result.accepted &&
                authorization.targetKey !== lastAccepted.receipt.targetKey;
            const consumed = { ...state.consumedActionAuthorizationIds };
            if (result.supported !== 'unknown' && !(result.supported === true && result.accepted)) delete consumed[action];
            publish({
                ...state,
                actionResults: { ...state.actionResults, [action]: result },
                consumedActionAuthorizationIds: consumed,
                awaitingSheetSwap: releasesSwap ? false : state.awaitingSheetSwap,
                phase: releasesSwap && state.phase === 'held'
                    ? state.context.ready && state.context.target && sourceConnected && configuredRevision === state.context.configRevision ? 'armed' : 'unavailable'
                    : state.phase,
            });
            return result;
        },
        dispose() {
            if (disposed) return;
            disposed = true;
            clearCountdown();
            clearQualityExpiry();
            clearAutoSteady();
            sourceAbort?.abort();
            acquisitionAbort?.abort();
            options.source.disconnect();
            busy = false;
            publish({ ...state, phase: 'disposed', countdownRemaining: null });
            listeners.clear();
        },
    };

    connectSource();
    return lifecycle;
}
