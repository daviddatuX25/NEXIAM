import {
    OMR_CAMERA_PROTOCOL_VERSION,
    type OmrCameraSource,
    type OmrCameraSourceConfig,
    type OmrCameraSourceEvent,
    type OmrCaptureOperation,
    type OmrCapturedFrame,
    type OmrCapturedStagedFrame,
} from './omrCaptureProtocol';
import { createOmrNativePeer, isFreshOmrPreviewFrame, type OmrCameraSignal, type OmrNativePeer } from './omrCameraMedia';
import { createOmrViewfinderSession } from './omrViewfinderSession';
import { createOmrQrCommandDetector, type OmrQrCommandDetector } from './omrCapturePlugins';
import type { ViewfinderResult } from './omrViewfinderWorker';
import { csrfHeaders } from './utils.js';

export type OmrSensorCommandKind = 'SET_CONFIG' | 'CAPTURE' | 'PAUSE' | 'STOP';
export type OmrSensorCommand = Readonly<{
    commandId: string;
    sequence: number;
    kind: OmrSensorCommandKind;
    operationId?: string;
    targetKey?: string;
    target?: OmrCaptureOperation['target'];
    sourceGeneration: number;
    configRevision: number;
    trigger?: OmrCaptureOperation['trigger'];
    mode?: OmrCaptureOperation['mode'];
    config?: Partial<OmrCameraSourceConfig>;
    expiresAt?: string;
}>;

export type OmrCameraEndpoints = Readonly<{
    create?: string;
    join: string;
    poll: string;
    signal: string;
    command: string;
    ack: string;
    heartbeat: string;
    status?: string;
    revoke?: string;
    rejoin?: string;
    frames: string;
    frameUrl?: string;
}>;

export type OmrCameraBootstrap = Readonly<{
    pairingId: string;
    pairCode?: string;
    sensorUrl?: string;
    protocolVersion: string;
    generation: number;
    token: string;
    target?: OmrCameraSourceConfig['target'];
    endpoints: OmrCameraEndpoints;
    iceServers: RTCIceServer[];
    nodeId?: string;
    config?: Partial<OmrCameraSourceConfig>;
}>;

type RequestOptions = {
    fetcher?: typeof fetch;
    staffGeneration?: string | null;
    role?: 'controller' | 'node';
    token?: string;
    generation?: number;
};

export type LocalCameraOptions = {
    mediaDevices?: Pick<MediaDevices, 'getUserMedia'>;
    workerFactory?: () => Worker;
    videoConstraints?: MediaTrackConstraints;
    qrDetector?: OmrQrCommandDetector;
};

type PairedSourceOptions = RequestOptions & {
    createPeerConnection?: (configuration?: RTCConfiguration) => RTCPeerConnection;
    now?: () => number;
    pollIntervalMs?: number;
    onDecodedFrame?: (video: HTMLVideoElement) => void;
    qrDetector?: OmrQrCommandDetector;
};

function staffGenerationFromStorage(): string | null {
    try {
        return window.localStorage.getItem('nexiam.staff.account-generation');
    } catch {
        return null;
    }
}

function requestHeaders(options: RequestOptions, includeJson = true): Headers {
    const headers = new Headers({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    for (const [key, value] of Object.entries(includeJson ? { ...csrfHeaders(), 'Content-Type': 'application/json' } : csrfHeaders())) {
        if (typeof value === 'string') headers.set(key, value);
    }
    const generation = options.staffGeneration ?? staffGenerationFromStorage();
    if (generation) headers.set('X-Staff-Generation', generation);
    if (options.role) headers.set('X-Omr-Camera-Role', options.role);
    if (options.token) headers.set('X-Omr-Camera-Token', options.token);
    if (options.generation !== undefined) headers.set('X-Omr-Camera-Generation', String(options.generation));
    return headers;
}
export class OmrCameraRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly code: string | null,
        public readonly details: Record<string, unknown> | null,
    ) {
        super(message);
        this.name = 'OmrCameraRequestError';
    }
}


async function cameraRequest<T>(url: string, init: RequestInit = {}, options: RequestOptions = {}): Promise<T> {
    const headers = requestHeaders(options, !(init.body instanceof FormData));
    new Headers(init.headers).forEach((value, name) => headers.set(name, value));
    const response = await (options.fetcher ?? fetch)(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'error',
        ...init,
        headers,
    });
    if (!response.ok) {
        const body = await response.clone().json().catch(() => null) as Record<string, unknown> | null;
        const detail = typeof body?.message === 'string' ? body.message : typeof body?.error === 'string' ? body.error : `Camera request failed (${response.status}).`;
        const code = typeof body?.code === 'string' ? body.code : typeof body?.error === 'string' ? body.error : null;
        const details = body?.details && typeof body.details === 'object' ? body.details as Record<string, unknown> : body;
        throw new OmrCameraRequestError(detail, response.status, code, details);
    }
    if (response.status === 204) return undefined as T;
    return response.json() as Promise<T>;
}

function endpointsFrom(value: unknown): OmrCameraEndpoints {
    if (!value || typeof value !== 'object') throw new Error('The server did not provide camera endpoints.');
    const source = value as Record<string, unknown>;
    for (const key of ['join', 'poll', 'signal', 'command', 'ack', 'heartbeat', 'frames']) {
        if (typeof source[key] !== 'string' || source[key].length === 0) throw new Error(`The server camera endpoint '${key}' is unavailable.`);
    }
    return source as unknown as OmrCameraEndpoints;
}

function isCameraSignal(value: unknown): value is OmrCameraSignal {
    if (!value || typeof value !== 'object') return false;
    const signal = value as Record<string, unknown>;
    return (signal.kind === 'offer' || signal.kind === 'answer' || signal.kind === 'ice') && Number.isSafeInteger(signal.generation) && Boolean(signal.payload && typeof signal.payload === 'object');
}

async function hashFile(file: Blob): Promise<string> {
    const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
    return [...new Uint8Array(digest)].map((value) => value.toString(16).padStart(2, '0')).join('');
}

function abortError(signal?: AbortSignal): DOMException {
    return signal?.reason instanceof DOMException ? signal.reason : new DOMException('Camera operation was cancelled.', 'AbortError');
}

export type OmrLocalCameraSource = OmrCameraSource & {
    readonly video: HTMLVideoElement | null;
    readonly latestAnalysis: ViewfinderResult | null;
    readonly available: boolean;
    readonly supportsQrCommands: boolean;
};

export type OmrPairedPhoneCameraSource = OmrCameraSource & {
    readonly supportsQrCommands: boolean;
};
export function createLocalOmrCameraSource(options: LocalCameraOptions = {}): OmrLocalCameraSource {
    const id = `local:${crypto.randomUUID()}`;
    let video: HTMLVideoElement | null = null;
    let stream: MediaStream | null = null;
    let listener: ((event: OmrCameraSourceEvent) => void) | null = null;
    let config: OmrCameraSourceConfig | null = null;
    let generation = 0;
    let analysis: ViewfinderResult | null = null;
    let workerAvailable = false;
    let inFlight = false;
    let qualitySequence = 0;
    let qrSequence = 0;
    let lockProgress = 0;
    let localReadyReported = false;
    let localReadyFrameCallback = 0;
    let localReadyAnimationFrame = 0;
    const ownsQrDetector = options.qrDetector === undefined;
    let qrDetector: OmrQrCommandDetector | null = options.qrDetector ?? null;
    const viewfinder = createOmrViewfinderSession({
        video: () => video,
        workerFactory: options.workerFactory,
        onResult(result) {
            analysis = result;
            if (!config || !listener || config.paused) return;
            const gates = result?.gates;
            if (result && !localReadyReported && !config.paused) {
                localReadyReported = true;
                listener({ type: 'status', status: 'ready' });
            }
            listener({
                type: 'quality',
                ready: Boolean(gates?.in_bounds && gates.angle_ok && gates.steady_ok && gates.lighting_ok),
                targetKey: config.target.targetKey,
                sourceGeneration: config.sourceGeneration,
                configRevision: config.configRevision,
                sequence: ++qualitySequence,
                lockProgress,
                result,
                relativeAgeMs: typeof result?.capturedAt === 'number' ? Math.max(0, performance.now() - result.capturedAt) : 1_000_000,
            });
        },
        onLockProgress(progress) {
            lockProgress = progress;
        },
        onUnavailable() {
            workerAvailable = false;
            waitForFreshLocalFrame();
        },
        onCapture: async () => false,
    });

    function waitForFreshLocalFrame(): void {
        const currentVideo = video;
        if (!currentVideo || !stream || !config || config.paused || localReadyReported) return;
        if (localReadyFrameCallback && 'cancelVideoFrameCallback' in currentVideo) {
            (currentVideo as HTMLVideoElement & { cancelVideoFrameCallback?: (handle: number) => void }).cancelVideoFrameCallback?.(localReadyFrameCallback);
        }
        if (localReadyAnimationFrame) cancelAnimationFrame(localReadyAnimationFrame);
        localReadyFrameCallback = 0;
        localReadyAnimationFrame = 0;
        let observationHandled = false;
        const markReady = (): void => {
            if (observationHandled) return;
            observationHandled = true;
            if (localReadyFrameCallback && 'cancelVideoFrameCallback' in currentVideo) {
                (currentVideo as HTMLVideoElement & { cancelVideoFrameCallback?: (handle: number) => void }).cancelVideoFrameCallback?.(localReadyFrameCallback);
            }
            if (localReadyAnimationFrame) cancelAnimationFrame(localReadyAnimationFrame);
            localReadyFrameCallback = 0;
            localReadyAnimationFrame = 0;
            if (currentVideo !== video || !stream || !config || config.paused || localReadyReported) return;
            if (currentVideo.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || !currentVideo.videoWidth || !currentVideo.videoHeight) {
                scheduleObservation();
                return;
            }
            localReadyReported = true;
            listener?.({ type: 'status', status: 'ready' });
        };
        const frameVideo = currentVideo as HTMLVideoElement & { requestVideoFrameCallback?: (callback: VideoFrameRequestCallback) => number };
        const scheduleObservation = (): void => {
            observationHandled = false;
            if (typeof frameVideo.requestVideoFrameCallback === 'function') {
                localReadyFrameCallback = frameVideo.requestVideoFrameCallback(markReady);
            }
            const previousTime = currentVideo.currentTime;
            const checkProgress = (): void => {
                localReadyAnimationFrame = 0;
                if (observationHandled || currentVideo !== video || !stream || !config || config.paused || localReadyReported) return;
                if ((currentVideo.currentTime > previousTime || currentVideo.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) && currentVideo.videoWidth > 0 && currentVideo.videoHeight > 0) {
                    markReady();
                    return;
                }
                localReadyAnimationFrame = requestAnimationFrame(checkProgress);
            };
            localReadyAnimationFrame = requestAnimationFrame(checkProgress);
        };
        scheduleObservation();
    }

    function stopStream(): void {
        viewfinder.stop();
        qrDetector?.pause();
        if (video && localReadyFrameCallback && 'cancelVideoFrameCallback' in video) {
            (video as HTMLVideoElement & { cancelVideoFrameCallback?: (handle: number) => void }).cancelVideoFrameCallback?.(localReadyFrameCallback);
        }
        if (localReadyAnimationFrame) cancelAnimationFrame(localReadyAnimationFrame);
        localReadyFrameCallback = 0;
        localReadyAnimationFrame = 0;
        for (const track of stream?.getTracks() ?? []) track.stop();
        stream = null;
        if (video) video.srcObject = null;
        analysis = null;
    }

    function configureQrDetector(): void {
        if (!config?.qrTriggerEnabled || !video || config.paused || !workerAvailable) {
            qrDetector?.pause();
            return;
        }
        qrDetector ??= createOmrQrCommandDetector();
        if (!qrDetector.supported) return;
        qrDetector.start(video, (command) => {
            if (!config || !listener) return;
            listener({ type: 'qr_intent', command, targetKey: config.target.targetKey, sourceGeneration: config.sourceGeneration, configRevision: config.configRevision, commandId: crypto.randomUUID(), sequence: ++qrSequence });
        }, (removal) => {
            if (!config || !listener) return;
            listener({ type: 'qr_card_removed', targetKey: config.target.targetKey, sourceGeneration: config.sourceGeneration, configRevision: config.configRevision, sequence: ++qrSequence, observedAt: removal.observedAt });
        });
    }


    return {
        id,
        kind: 'local',
        get video() { return video; },
        get latestAnalysis() { return analysis; },
        get available() { return workerAvailable; },
        get supportsQrCommands() {
            return qrDetector?.supported ?? ('BarcodeDetector' in globalThis ||
                typeof Worker !== 'undefined' && typeof createImageBitmap === 'function');
        },
        async connect(onEvent, signal) {
            listener = onEvent;
            localReadyReported = false;
            generation += 1;
            const current = generation;
            onEvent({ type: 'status', status: 'connecting' });
            if (signal?.aborted) throw abortError(signal);
            const mediaDevices = options.mediaDevices ?? navigator.mediaDevices;
            if (!mediaDevices?.getUserMedia) throw new Error('Camera access is unavailable in this browser or origin.');
            const opened = await mediaDevices.getUserMedia({ audio: false, video: options.videoConstraints ?? { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
            if (current !== generation || signal?.aborted) {
                for (const track of opened.getTracks()) track.stop();
                throw abortError(signal);
            }
            stream = opened;
            if (video) {
                video.srcObject = opened;
                await video.play().catch((error) => {
                    stopStream();
                    throw error;
                });
            }
            if (current !== generation || signal?.aborted) {
                stopStream();
                throw abortError(signal);
            }
            workerAvailable = viewfinder.start();
            configureQrDetector();
            waitForFreshLocalFrame();
        },
        attachPreview(element) {
            video = element;
            if (video && stream) {
                video.srcObject = stream;
                void video.play().then(() => {
                    waitForFreshLocalFrame();
                    configureQrDetector();
                }).catch(() => listener?.({ type: 'status', status: 'error', message: 'Tap to enable the camera preview.' }));
            }
        },
        async configure(next) {
            config = next;
            viewfinder.setLockDurationMs(next.steadyDelayMs);
            viewfinder.setAutoEnabled(false);
            if (next.paused) {
                localReadyReported = false;
                viewfinder.stop();
                workerAvailable = false;
                if (video && localReadyFrameCallback && 'cancelVideoFrameCallback' in video) {
                    (video as HTMLVideoElement & { cancelVideoFrameCallback?: (handle: number) => void }).cancelVideoFrameCallback?.(localReadyFrameCallback);
                }
                if (localReadyAnimationFrame) cancelAnimationFrame(localReadyAnimationFrame);
                localReadyFrameCallback = 0;
                localReadyAnimationFrame = 0;
                analysis = null;
                qrDetector?.pause();
                listener?.({ type: 'status', status: 'paused' });
                return;
            }
            localReadyReported = false;
            analysis = null;
            workerAvailable = viewfinder.start();
            if (stream && video) listener?.({ type: 'status', status: 'connecting', message: 'Waiting for a fresh camera frame.' });
            configureQrDetector();
            waitForFreshLocalFrame();
        },
        async acquire(operation, signal) {
            if (!stream || !video || !config || config.paused || operation.target.targetKey !== config.target.targetKey || operation.sourceGeneration !== config.sourceGeneration || operation.configRevision !== config.configRevision) throw new Error('The local camera is not ready for this target.');
            if (inFlight) throw new Error('A local camera acquisition is already in progress.');
            if (signal?.aborted) throw abortError(signal);
            inFlight = true;
            const activeGeneration = generation;
            try {
                if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || !video.videoWidth || !video.videoHeight) throw new Error('The camera has no fresh frame to capture.');
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d');
                if (!context) throw new Error('The camera frame could not be read.');
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise<Blob>((resolve, reject) => canvas.toBlob((value) => value ? resolve(value) : reject(new Error('The camera image could not be encoded.')), 'image/jpeg', 0.94));
                if (signal?.aborted || generation !== activeGeneration || !config || config.paused ||
                    config.target.targetKey !== operation.target.targetKey ||
                    config.sourceGeneration !== operation.sourceGeneration ||
                    config.configRevision !== operation.configRevision) throw abortError(signal);
                const file = new File([blob], `omr-${operation.operationId}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
                const gates = analysis?.gates;
                const clientQuad = analysis && typeof analysis.capturedAt === 'number' && gates?.in_bounds && gates.angle_ok && gates.steady_ok && gates.lighting_ok && performance.now() - analysis.capturedAt <= 250 ? analysis.quad ?? undefined : undefined;
                return { kind: 'raw', file, targetKey: operation.target.targetKey, inputHash: await hashFile(file), width: canvas.width, height: canvas.height, orientation: 1, sourceGeneration: operation.sourceGeneration, clientQuad } satisfies OmrCapturedFrame;
            } finally {
                inFlight = false;
            }
        },
        disconnect() {
            generation += 1;
            stopStream();
            if (ownsQrDetector) {
                qrDetector?.dispose();
                qrDetector = null;
            } else {
                qrDetector?.pause();
            }
            listener?.({ type: 'status', status: 'disconnected' });
            listener = null;
            video = null;
        },
    };
}


export type OmrPairedPhoneSourceOptions = PairedSourceOptions;

export function createPairedPhoneCameraSource(bootstrap: OmrCameraBootstrap, options: OmrPairedPhoneSourceOptions = {}): OmrPairedPhoneCameraSource {
    if (bootstrap.protocolVersion !== OMR_CAMERA_PROTOCOL_VERSION) throw new Error('The paired camera protocol version is not supported.');
    const endpoints = endpointsFrom(bootstrap.endpoints);
    const auth: RequestOptions = { ...options, role: 'controller', token: bootstrap.token, generation: bootstrap.generation };
    const now = options.now ?? (() => performance.now());
    const pollDelay = options.pollIntervalMs ?? 350;
    const waiters = new Map<string, { resolve(frame: OmrCapturedStagedFrame): void; reject(error: Error): void; operation: OmrCaptureOperation; timer: ReturnType<typeof setTimeout> }>();
    const configAcks = new Map<string, { resolve(): void; reject(error: Error): void; timer: ReturnType<typeof setTimeout> }>();
    let peer: OmrNativePeer | null = null;
    let preview: HTMLVideoElement | null = null;
    let listener: ((event: OmrCameraSourceEvent) => void) | null = null;
    let remoteStream: MediaStream | null = null;
    let config: OmrCameraSourceConfig | null = null;
    let pollController: AbortController | null = null;
    let pollTimer: ReturnType<typeof setTimeout> | null = null;
    let commandSequence = 0;
    let signalSequence = 0;
    let observationSequence = 0;
    let heartbeatSequence = 0;
    let stopped = false;
    let pollBusy = false;
    let lastDecodedAt = 0;
    let lastDecodedCurrentTime = -1;
    let failures = 0;
    let decodedFrameCallback = 0;
    let fallbackFrameCallback = 0;
    let nodeHeartbeatAgeMs = Number.POSITIVE_INFINITY;
    let nodeHeartbeatObservedAt = Number.NEGATIVE_INFINITY;
    let phoneQrSupported = false;
    let offerStarted = false;
    let previewReadyReported = false;
    let configurationRequest = 0;

    function nodeHeartbeatIsFresh(): boolean {
        return nodeHeartbeatAgeMs + Math.max(0, now() - nodeHeartbeatObservedAt) <= 15000;
    }

    function waitForConfigAck(commandId: string): Promise<void> {
        return new Promise<void>((resolve, reject) => {
            const timer = setTimeout(() => {
                configAcks.delete(commandId);
                reject(new Error('The phone did not confirm the current camera configuration.'));
            }, 5000);
            configAcks.set(commandId, { resolve, reject, timer });
        });
    }

    function acceptConfigAck(value: unknown): void {
        if (!value || typeof value !== 'object') return;
        const ack = value as Record<string, unknown>;
        if (typeof ack.command_id !== 'string') return;
        const pending = configAcks.get(ack.command_id);
        if (!pending) return;
        clearTimeout(pending.timer);
        configAcks.delete(ack.command_id);
        if (ack.status === 'applied') pending.resolve();
        else pending.reject(new Error('The phone rejected the camera configuration.'));
    }
    function stopPolling(): void {
        if (pollTimer !== null) clearTimeout(pollTimer);
        pollTimer = null;
        pollController?.abort();
        pollController = null;
    }

    async function sendSignal(message: OmrCameraSignal, signal?: AbortSignal): Promise<void> {
        await cameraRequest(endpoints.signal, { method: 'POST', body: JSON.stringify({ kind: message.kind, generation: message.generation, payload: message.payload, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }), signal }, auth);
    }

    function schedulePoll(delay = pollDelay): void {
        if (stopped || pollTimer !== null || document.visibilityState !== 'visible') return;
        pollTimer = setTimeout(() => {
            pollTimer = null;
            void poll();
        }, delay);
    }

    async function reportHeartbeat(): Promise<void> {
        if (!config || stopped) return;
        await cameraRequest(endpoints.heartbeat, { method: 'POST', body: JSON.stringify({ protocol_version: OMR_CAMERA_PROTOCOL_VERSION, ready: Boolean(peer && peer.connection.connectionState === 'connected' && isFreshOmrPreviewFrame(lastDecodedAt, now()) && nodeHeartbeatIsFresh()), source_generation: config.sourceGeneration, target_key: config.target.targetKey, sequence: ++heartbeatSequence }) }, auth);
    }

    function receiveCaptureAck(value: unknown): void {
        if (!value || typeof value !== 'object') return;
        const ack = value as Record<string, unknown>;
        if (typeof ack.command_id !== 'string' || ack.status !== 'rejected') return;
        const waiter = waiters.get(ack.command_id);
        if (!waiter) return;
        const targetKey = typeof ack.target_key === 'string' ? ack.target_key : null;
        const sourceGeneration = Number(ack.source_generation);
        const configRevision = Number(ack.config_revision);
        if ((targetKey !== null && targetKey !== waiter.operation.target.targetKey) ||
            sourceGeneration !== waiter.operation.sourceGeneration ||
            configRevision !== waiter.operation.configRevision) return;
        waiters.delete(ack.command_id);
        clearTimeout(waiter.timer);
        waiter.reject(new Error(typeof ack.reason === 'string' ? ack.reason : 'The phone rejected the camera capture.'));
    }

    function receiveStagedFrame(value: unknown, lastAck: unknown): void {
        if (!value || typeof value !== 'object') return;
        const staged = value as Record<string, unknown>;
        if (typeof staged.frame_id !== 'string' || typeof staged.command_id !== 'string') return;
        const waiter = waiters.get(staged.command_id);
        if (!waiter) return;
        waiters.delete(staged.command_id);
        clearTimeout(waiter.timer);
        const ack = lastAck && typeof lastAck === 'object' && (lastAck as Record<string, unknown>).command_id === staged.command_id ? lastAck as Record<string, unknown> : {};
        const targetKey = String(staged.target_key ?? ack.target_key ?? '');
        const sourceGeneration = Number(staged.source_generation ?? ack.source_generation);
        const inputHash = String(staged.input_hash ?? ack.input_hash ?? '');
        const width = Number(staged.width ?? ack.width);
        const height = Number(staged.height ?? ack.height);
        const orientation = Number(staged.orientation ?? ack.orientation ?? 1);
        const previewUrl = typeof staged.preview_url === 'string' ? staged.preview_url : '';
        if (targetKey !== waiter.operation.target.targetKey || sourceGeneration !== waiter.operation.sourceGeneration) {
            waiter.reject(new Error('The staged original belongs to a stale camera generation or target.'));
            return;
        }
        if (!/^[a-f0-9]{64}$/i.test(inputHash) || !Number.isSafeInteger(width) || width < 1 || !Number.isSafeInteger(height) || height < 1 || !Number.isSafeInteger(orientation) || orientation < 1 || orientation > 8 || previewUrl.length === 0) {
            waiter.reject(new Error('The server did not return complete original-still metadata.'));
            return;
        }
        waiter.resolve({ kind: 'staged', frameId: staged.frame_id, previewUrl, commandId: staged.command_id, pairingId: bootstrap.pairingId, targetKey, inputHash, width, height, orientation, sourceGeneration });
    }

    async function poll(): Promise<void> {
        if (stopped || pollBusy || document.visibilityState !== 'visible') return;
        pollBusy = true;
        const controller = new AbortController();
        pollController = controller;
        try {
            const url = new URL(endpoints.poll, window.location.origin);
            url.searchParams.set('after_sequence', String(commandSequence));
            url.searchParams.set('after_signal_sequence', String(signalSequence));
            url.searchParams.set('after_observation_sequence', String(observationSequence));
            const pollStartedAt = now();
            const state = await cameraRequest<Record<string, unknown>>(url.toString(), { signal: controller.signal }, auth);
            failures = 0;
            acceptConfigAck(state.last_ack);
            receiveCaptureAck(state.last_ack);
            nodeHeartbeatAgeMs = Number(state.heartbeat_age_ms);
            if (!Number.isFinite(nodeHeartbeatAgeMs) || nodeHeartbeatAgeMs < 0) nodeHeartbeatAgeMs = Number.POSITIVE_INFINITY;
            nodeHeartbeatObservedAt = now();
            phoneQrSupported = state.qr_supported === true;
            if (!offerStarted && nodeHeartbeatIsFresh() && peer) {
                await peer.start(controller.signal);
                offerStarted = true;
            }
            if (Array.isArray(state.signals)) {
                for (const entry of state.signals) {
                    if (!isCameraSignal(entry)) continue;
                    await peer?.receive(entry);
                    signalSequence = Math.max(signalSequence, Number((entry as Record<string, unknown>).sequence ?? signalSequence));
                }
            }
            if (Array.isArray(state.observations)) {
                const observations = (state.observations as Record<string, unknown>[]).sort((left, right) => Number(left.sequence ?? 0) - Number(right.sequence ?? 0));
                for (const observation of observations) {
                    const observedSequence = Number(observation.sequence ?? observation.observation_sequence ?? 0);
                    if (!Number.isSafeInteger(observedSequence) || observedSequence <= observationSequence) continue;
                    observationSequence = observedSequence;
                    if (observation.qr_supported === true) phoneQrSupported = true;
                    if (!config || observation.target_key !== config.target.targetKey || Number(observation.source_generation) !== config.sourceGeneration || Number(observation.config_revision) !== config.configRevision) continue;
                    const relativeAgeMs = Number(observation.age_ms ?? 1000000) + Math.max(0, now() - pollStartedAt);
                    const hasQuality = observation.kind === 'quality' || Boolean(observation.quality || observation.result);
                    if (hasQuality) {
                        const gates = observation.quality && typeof observation.quality === 'object' ? observation.quality as ViewfinderResult['gates'] : undefined;
                        const result = observation.result && typeof observation.result === 'object' ? observation.result as ViewfinderResult : null;
                        listener?.({
                            type: 'quality',
                            ready: Boolean(observation.ready === true && relativeAgeMs <= 250 && gates?.in_bounds && gates.angle_ok && gates.steady_ok && gates.lighting_ok),
                            targetKey: config.target.targetKey,
                            sourceGeneration: config.sourceGeneration,
                            configRevision: config.configRevision,
                            sequence: observedSequence,
                            lockProgress: Number(observation.lock_progress ?? 0),
                            relativeAgeMs: Number.isFinite(relativeAgeMs) ? relativeAgeMs : 1000000,
                            result,
                        });
                    }
                    const qrFreshnessMs = Math.max(1000, pollDelay * 2);
                    if (relativeAgeMs <= qrFreshnessMs && observation.kind === 'qr_intent' && ['CAPTURE', 'NEXT_PAGE', 'NEXT_SET', 'REDO_LAST'].includes(String(observation.command))) {
                        listener?.({ type: 'qr_intent', command: observation.command as 'CAPTURE' | 'NEXT_PAGE' | 'NEXT_SET' | 'REDO_LAST', targetKey: config.target.targetKey, sourceGeneration: config.sourceGeneration, configRevision: config.configRevision, commandId: String(observation.command_id ?? crypto.randomUUID()), sequence: observedSequence });
                    }
                    if (relativeAgeMs <= qrFreshnessMs && (observation.qr_card_removed === true || observation.kind === 'qr_card_removed')) {
                        listener?.({ type: 'qr_card_removed', targetKey: config.target.targetKey, sourceGeneration: config.sourceGeneration, configRevision: config.configRevision, sequence: observedSequence, observedAt: now() });
                    }
                }
            }
            receiveStagedFrame(state.staged_frame, state.last_ack);
            if (Array.isArray(state.commands)) {
                for (const command of state.commands as Record<string, unknown>[]) commandSequence = Math.max(commandSequence, Number(command.sequence ?? commandSequence));
            }
            const nodeIsFresh = nodeHeartbeatIsFresh();
            if (!nodeIsFresh) {
                previewReadyReported = false;
                listener?.({ type: 'status', status: 'paused', message: 'Phone heartbeat expired. Wait for the phone to reconnect before capturing.' });
            } else if (peer?.connection.connectionState === 'connected' && !isFreshOmrPreviewFrame(lastDecodedAt, now())) {
                previewReadyReported = false;
                listener?.({ type: 'status', status: 'error', message: 'Live camera preview is stale. Waiting for a fresh decoded phone frame.' });
            }
            await reportHeartbeat().catch(() => undefined);
        } catch (error) {
            if (!controller.signal.aborted && !stopped) {
                failures += 1;
                previewReadyReported = false;
                nodeHeartbeatAgeMs = Number.POSITIVE_INFINITY;
                listener?.({ type: 'status', status: 'error', message: error instanceof Error ? error.message : 'Camera connection interrupted.' });
            }
        } finally {
            if (pollController === controller) pollController = null;
            pollBusy = false;
            schedulePoll(Math.min(5000, pollDelay * 2 ** Math.min(failures, 4)));
        }
    }

    function observeDecodedFrames(): void {
        const video = preview;
        if (!video || stopped || decodedFrameCallback || fallbackFrameCallback) return;
        const recordProgress = (): void => {
            if (stopped) return;
            if (video.currentTime > lastDecodedCurrentTime) {
                lastDecodedCurrentTime = video.currentTime;
                lastDecodedAt = now();
                options.onDecodedFrame?.(video);
                if (config && !config.paused && nodeHeartbeatIsFresh() && !previewReadyReported) {
                    previewReadyReported = true;
                    listener?.({ type: 'configured', config });
                    listener?.({ type: 'status', status: 'ready' });
                }
            }
            observeDecodedFrames();
        };
        const videoFrameApi = video as HTMLVideoElement & { requestVideoFrameCallback?: (callback: VideoFrameRequestCallback) => number };
        if (typeof videoFrameApi.requestVideoFrameCallback === 'function') {
            decodedFrameCallback = videoFrameApi.requestVideoFrameCallback(() => {
                decodedFrameCallback = 0;
                recordProgress();
            });
            return;
        }
        fallbackFrameCallback = requestAnimationFrame(() => {
            fallbackFrameCallback = 0;
            recordProgress();
        });
    }

    function visibilityChanged(): void {
        if (document.visibilityState !== 'visible') {
            stopPolling();
            listener?.({ type: 'status', status: 'paused', message: 'Camera polling paused while this tab is hidden.' });
            return;
        }
        lastDecodedAt = 0;
        previewReadyReported = false;
        schedulePoll(0);
    }

    async function sendConfigCommand(command: 'SET_CONFIG' | 'PAUSE', payload: Record<string, unknown>): Promise<Record<string, unknown>> {
        const commandId = String(payload.command_id);
        const acknowledgement = waitForConfigAck(commandId);
        try {
            const result = await cameraRequest<Record<string, unknown>>(endpoints.command, { method: 'POST', body: JSON.stringify(payload) }, auth);
            schedulePoll(0);
            await acknowledgement;
            return result;
        } catch (error) {
            const pending = configAcks.get(commandId);
            if (pending) {
                clearTimeout(pending.timer);
                configAcks.delete(commandId);
            }
            void acknowledgement.catch(() => undefined);
            throw error;
        }
    }
    function normalizeServerConfig(
        response: Record<string, unknown>,
        requested: OmrCameraSourceConfig,
    ): OmrCameraSourceConfig {
        const raw = (response.config && typeof response.config === 'object' ? response.config : response) as Record<string, unknown>;
        const rawTarget = (raw.target && typeof raw.target === 'object' ? raw.target : response.target) as Record<string, unknown> | undefined;
        const targetKey = rawTarget && (typeof rawTarget.targetKey === 'string' ? rawTarget.targetKey : typeof rawTarget.target_key === 'string' ? rawTarget.target_key : null);
        const workbenchId = rawTarget && (typeof rawTarget.workbenchId === 'string' ? rawTarget.workbenchId : typeof rawTarget.workbench_id === 'string' ? rawTarget.workbench_id : null);
        const pageNo = Number(rawTarget?.pageNo ?? rawTarget?.page_no);
        if (!targetKey || !workbenchId || !Number.isSafeInteger(pageNo) || pageNo < 1) throw new Error('The server did not return a complete authorized camera target.');
        const target = Object.freeze({
            workbenchId,
            clientCaptureId: (rawTarget?.clientCaptureId ?? rawTarget?.client_capture_id ?? null) as string | null,
            pageNo,
            captureRevision: (rawTarget?.captureRevision ?? rawTarget?.capture_revision ?? null) as string | number | null,
            instanceId: (rawTarget?.instanceId ?? rawTarget?.instance_id ?? null) as string | number | null,
            sheetId: (rawTarget?.sheetId ?? rawTarget?.sheet_id ?? null) as string | number | null,
            targetKey,
        });
        const sourceGeneration = Number(response.source_generation ?? raw.sourceGeneration ?? raw.source_generation);
        const configRevision = Number(response.config_revision ?? raw.configRevision ?? raw.config_revision);
        const mode = String(raw.mode ?? requested.mode);
        const batchTrigger = String(raw.batchTrigger ?? raw.batch_trigger ?? requested.batchTrigger);
        const registrationMode = String(raw.registrationMode ?? raw.registration_mode ?? requested.registrationMode);
        const steadyDelayMs = Number(raw.steadyDelayMs ?? raw.steady_delay_ms ?? requested.steadyDelayMs);
        if (!Number.isSafeInteger(sourceGeneration) || !Number.isSafeInteger(configRevision) || !['manual', 'auto', 'batch'].includes(mode) || !['auto_steady', 'manual_qr'].includes(batchTrigger) || !['fiducial_anchored', 'document_bound'].includes(registrationMode) || ![150, 300, 500, 800].includes(steadyDelayMs)) throw new Error('The server returned an invalid camera configuration.');
        return Object.freeze({
            target,
            sourceGeneration,
            configRevision,
            registrationMode: registrationMode as OmrCameraSourceConfig['registrationMode'],
            pageCount: Number(raw.pageCount ?? raw.page_count ?? requested.pageCount),
            mode: mode as OmrCameraSourceConfig['mode'],
            batchTrigger: batchTrigger as OmrCameraSourceConfig['batchTrigger'],
            steadyDelayMs: steadyDelayMs as OmrCameraSourceConfig['steadyDelayMs'],
            qrTriggerEnabled: Boolean(raw.qrTriggerEnabled ?? raw.qr_trigger_enabled ?? requested.qrTriggerEnabled),
            paused: Boolean(raw.paused),
        });
    }

    function attachRemotePreview(): void {
        if (!preview || !remoteStream) return;
        preview.srcObject = remoteStream;
        void preview.play().then(() => observeDecodedFrames()).catch(() => listener?.({ type: 'status', status: 'error', message: 'Tap the desktop preview to allow camera playback.' }));
    }

    return {
        id: `paired:${bootstrap.pairingId}`,
        kind: 'paired_phone',
        get supportsQrCommands() { return phoneQrSupported; },
        async connect(onEvent, signal) {
            if (stopped) throw new Error('The paired camera source has been disconnected.');
            if (bootstrap.endpoints === undefined) throw new Error('Camera endpoints were not projected by the server.');
            listener = onEvent;
            onEvent({ type: 'status', status: 'connecting' });
            const createPeerConnection = options.createPeerConnection ?? ((configuration?: RTCConfiguration) => new RTCPeerConnection(configuration));
            peer = createOmrNativePeer('controller', bootstrap.generation, bootstrap.iceServers, {
                createPeerConnection,
                send: sendSignal,
                onPreview(stream) {
                    remoteStream = stream;
                    attachRemotePreview();
                },
                onState(state) {
                    if (state === 'connected') listener?.({ type: 'status', status: 'connecting', message: 'WebRTC connected; waiting for a decoded camera frame.' });
                    if (state === 'failed' || state === 'disconnected' || state === 'closed') listener?.({ type: 'status', status: 'disconnected', message: 'Phone camera connection lost. Reconnect the phone.' });
                },
                onError(error) { listener?.({ type: 'status', status: 'error', message: error.message }); },
            });
            signal?.addEventListener('abort', () => this.disconnect(), { once: true });
            peer.connection.addTransceiver('video', { direction: 'recvonly' });
            document.addEventListener('visibilitychange', visibilityChanged);
            schedulePoll(0);
        },
        attachPreview(video) {
            const previousPreview = preview;
            preview = video;
            if (previousPreview && previousPreview !== video) previousPreview.srcObject = null;
            if (!video) {
                lastDecodedAt = 0;
                return;
            }
            if (remoteStream) {
                attachRemotePreview();
                return;
            }
            if (peer?.connection.remoteDescription) observeDecodedFrames();
        },
        async configure(next) {
            const request = ++configurationRequest;
            if (next.paused) {
                config = { ...next, paused: true };
                const commandId = crypto.randomUUID();
                const response = await sendConfigCommand('PAUSE', {
                    protocol_version: OMR_CAMERA_PROTOCOL_VERSION,
                    command: 'PAUSE',
                    command_id: commandId,
                    source_generation: next.sourceGeneration,
                    config_revision: next.configRevision,
                    config: { paused: true },
                });
                if (request !== configurationRequest || stopped) return;
                const pausedConfig = normalizeServerConfig(response, { ...next, paused: true });
                config = { ...pausedConfig, paused: true };
                previewReadyReported = false;
                lastDecodedAt = 0;
                lastDecodedCurrentTime = preview?.currentTime ?? -1;
                listener?.({ type: 'configured', config });
                listener?.({ type: 'status', status: 'paused' });
                return;
            }
            const commandId = crypto.randomUUID();
            const response = await sendConfigCommand('SET_CONFIG', {
                protocol_version: OMR_CAMERA_PROTOCOL_VERSION,
                command: 'SET_CONFIG',
                command_id: commandId,
                source_generation: next.sourceGeneration,
                config_revision: next.configRevision,
                config: {
                    registration_mode: next.registrationMode,
                    page_count: next.pageCount,
                    mode: next.mode,
                    batch_trigger: next.batchTrigger,
                    steady_delay_ms: next.steadyDelayMs,
                    qr_trigger_enabled: next.qrTriggerEnabled,
                    paused: false,
                },
            });
            const configured = normalizeServerConfig(response, next);
            if (request !== configurationRequest || stopped) return;
            config = configured;
            previewReadyReported = false;
            lastDecodedAt = 0;
            lastDecodedCurrentTime = preview?.currentTime ?? -1;
            listener?.({ type: 'status', status: 'connecting', message: 'Phone configured; waiting for a new decoded preview frame.' });
            listener?.({ type: 'configured', config: configured });
        },
        async acquire(operation, signal): Promise<OmrCapturedFrame> {
            if (!config || config.paused || operation.target.targetKey !== config.target.targetKey || operation.sourceGeneration !== config.sourceGeneration || operation.configRevision !== config.configRevision || !peer || peer.connection.connectionState !== 'connected' || !isFreshOmrPreviewFrame(lastDecodedAt, now()) || !nodeHeartbeatIsFresh()) throw new Error('The paired camera preview is not fresh for this target.');
            if (signal?.aborted) throw abortError(signal);
            const commandId = crypto.randomUUID();
            const promise = new Promise<OmrCapturedStagedFrame>((resolve, reject) => {
                const timer = setTimeout(() => {
                    waiters.delete(commandId);
                    reject(new Error('The phone did not stage the reserved original before the frame deadline.'));
                }, 10 * 60 * 1000);
                waiters.set(commandId, { resolve, reject, operation, timer });
            });
            void promise.catch(() => undefined);
            const cancel = () => {
                const waiter = waiters.get(commandId);
                if (!waiter) return;
                clearTimeout(waiter.timer);
                waiters.delete(commandId);
                waiter.reject(abortError(signal));
            };
            signal?.addEventListener('abort', cancel, { once: true });
            try {
                await cameraRequest(endpoints.command, { method: 'POST', body: JSON.stringify({ protocol_version: OMR_CAMERA_PROTOCOL_VERSION, command: 'CAPTURE', command_id: commandId, operation_id: operation.operationId, target: operation.target, target_key: operation.target.targetKey, source_generation: operation.sourceGeneration, config_revision: operation.configRevision, trigger: operation.trigger, mode: operation.mode }) }, auth);
                schedulePoll(0);
                return await promise;
            } catch (error) {
                cancel();
                void promise.catch(() => undefined);
                throw error;
            } finally {
                signal?.removeEventListener('abort', cancel);
            }
        },
        disconnect() {
            if (stopped) return;
            stopped = true;
            stopPolling();
            document.removeEventListener('visibilitychange', visibilityChanged);
            if (preview && 'cancelVideoFrameCallback' in preview && decodedFrameCallback) preview.cancelVideoFrameCallback(decodedFrameCallback);
            if (fallbackFrameCallback) cancelAnimationFrame(fallbackFrameCallback);
            peer?.close();
            peer = null;
            for (const waiter of waiters.values()) {
                clearTimeout(waiter.timer);
                waiter.reject(new Error('Camera source disconnected before the original was staged.'));
            }
            waiters.clear();
            for (const pending of configAcks.values()) {
                clearTimeout(pending.timer);
                pending.reject(new Error('Camera source disconnected before the phone configuration was confirmed.'));
            }
            configAcks.clear();
            listener?.({ type: 'status', status: 'disconnected' });
            listener = null;
            if (preview) preview.srcObject = null;
            preview = null;
            remoteStream = null;
        },
    };
}

export type OmrPhoneJoinResult = Readonly<{
    pairingId: string;
    nodeId: string;
    nodeToken: string;
    generation: number;
    controllerGeneration: number;
    protocolVersion: string;
    endpoints: OmrCameraEndpoints;
    iceServers: RTCIceServer[];
    config?: Partial<OmrCameraSourceConfig>;
}>;

export async function joinPairedPhoneSession(endpoint: string, pairCode: string, options: RequestOptions = {}): Promise<OmrPhoneJoinResult> {
    const result = await cameraRequest<Record<string, unknown>>(endpoint, { method: 'POST', body: JSON.stringify({ pair_code: pairCode, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }) }, options);
    if (result.protocol_version !== OMR_CAMERA_PROTOCOL_VERSION || typeof result.pairing_id !== 'string' || typeof result.node_id !== 'string' || typeof result.node_token !== 'string' || !Number.isSafeInteger(result.generation) || !Number.isSafeInteger(result.controller_generation)) throw new Error('The server returned an invalid phone pairing response.');
    return { pairingId: result.pairing_id, nodeId: result.node_id, nodeToken: result.node_token, generation: Number(result.generation), controllerGeneration: Number(result.controller_generation), protocolVersion: OMR_CAMERA_PROTOCOL_VERSION, endpoints: endpointsFrom(result.endpoints), iceServers: Array.isArray(result.ice_servers) ? result.ice_servers as RTCIceServer[] : [], config: result.config as Partial<OmrCameraSourceConfig> | undefined };
}

export async function createPairedPhoneSessionClient(createEndpoint: string, payload: Record<string, unknown> = {}, options: RequestOptions = {}): Promise<OmrCameraBootstrap> {
    const result = await cameraRequest<Record<string, unknown>>(createEndpoint, { method: 'POST', body: JSON.stringify({ ...payload, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }) }, options);
    if (result.protocol_version !== OMR_CAMERA_PROTOCOL_VERSION || typeof result.pairing_id !== 'string' || typeof result.controller_token !== 'string' || !Number.isSafeInteger(result.generation) || typeof result.pair_code !== 'string') throw new Error('The server returned an invalid camera pairing response.');
    return { pairingId: result.pairing_id, pairCode: result.pair_code, sensorUrl: typeof result.sensor_url === 'string' ? result.sensor_url : undefined, protocolVersion: OMR_CAMERA_PROTOCOL_VERSION, generation: Number(result.generation), token: result.controller_token, target: result.target as OmrCameraBootstrap['target'], endpoints: endpointsFrom(result.endpoints), iceServers: Array.isArray(result.ice_servers) ? result.ice_servers as RTCIceServer[] : [], config: result.config as Partial<OmrCameraSourceConfig> | undefined };
}
export async function revokePairedPhoneSession(bootstrap: OmrCameraBootstrap, options: RequestOptions = {}): Promise<void> {
    const endpoint = bootstrap.endpoints.revoke;
    if (!endpoint) throw new Error('The server did not project a camera revoke endpoint.');
    const auth: RequestOptions = { ...options, role: 'controller', token: bootstrap.token, generation: bootstrap.generation };
    await cameraRequest(endpoint, { method: 'POST', body: JSON.stringify({ protocol_version: OMR_CAMERA_PROTOCOL_VERSION }) }, auth);
}

export async function rejoinPairedPhoneSession(
    bootstrap: OmrCameraBootstrap,
    selection: Readonly<{ capture_sheet_id?: number; capture_page_no?: number }> = {},
    options: RequestOptions = {},
): Promise<OmrCameraBootstrap> {
    const endpoint = bootstrap.endpoints.rejoin;
    if (!endpoint) throw new Error('The server did not project a phone replacement endpoint.');
    const result = await cameraRequest<Record<string, unknown>>(endpoint, {
        method: 'POST',
        body: JSON.stringify({ ...selection, confirmed: true, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }),
    }, { ...options, role: 'controller', token: bootstrap.token, generation: bootstrap.generation });
    if (result.protocol_version !== OMR_CAMERA_PROTOCOL_VERSION || result.pairing_id !== bootstrap.pairingId ||
        result.controller_generation !== bootstrap.generation || typeof result.pair_code !== 'string' ||
        typeof result.sensor_url !== 'string' || !result.config || typeof result.config !== 'object') {
        throw new Error('The server did not return the current phone replacement configuration.');
    }
    const config = result.config as Partial<OmrCameraSourceConfig>;
    if (!Number.isSafeInteger(config.sourceGeneration) || !Number.isSafeInteger(config.configRevision) || !config.target) {
        throw new Error('The phone replacement is missing its authoritative source configuration.');
    }
    return {
        ...bootstrap,
        pairCode: result.pair_code,
        sensorUrl: result.sensor_url,
        endpoints: endpointsFrom(result.endpoints),
        config,
        target: config.target,
        iceServers: Array.isArray(result.ice_servers) ? result.ice_servers as RTCIceServer[] : bootstrap.iceServers,
    };
}


export async function fetchPairedFrame(url: string, auth: RequestOptions, signal?: AbortSignal): Promise<Blob> {
    const response = await (auth.fetcher ?? fetch)(url, { credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal, headers: requestHeaders(auth, false) });
    if (!response.ok) throw new Error(`Staged original unavailable (${response.status}).`);
    return response.blob();
}

export type OmrPhoneSensorClient = {
    readonly joined: OmrPhoneJoinResult;
    poll(afterSequence: number, afterSignalSequence: number, signal?: AbortSignal): Promise<Record<string, unknown>>;
    sendSignal(message: OmrCameraSignal, signal?: AbortSignal): Promise<void>;
    acknowledge(payload: Record<string, unknown>, signal?: AbortSignal): Promise<Record<string, unknown>>;
    stage(file: File, payload: { commandId: string; frameId: string; targetKey: string; clientQuad?: unknown }, signal?: AbortSignal): Promise<{ frame_id: string; preview_url: string; upload_expires_at?: string }>;
    heartbeat(payload: Record<string, unknown>, signal?: AbortSignal): Promise<void>;
};

export function createOmrPhoneSensorClient(joined: OmrPhoneJoinResult, options: RequestOptions = {}): OmrPhoneSensorClient {
    if (joined.protocolVersion !== OMR_CAMERA_PROTOCOL_VERSION) throw new Error('The paired camera protocol version is not supported.');
    const endpoints = endpointsFrom(joined.endpoints);
    const auth: RequestOptions = { ...options, role: 'node', token: joined.nodeToken, generation: joined.generation };
    return {
        joined,
        async poll(afterSequence, afterSignalSequence, signal) {
            const url = new URL(endpoints.poll, window.location.origin);
            url.searchParams.set('after_sequence', String(afterSequence));
            url.searchParams.set('after_signal_sequence', String(afterSignalSequence));
            return cameraRequest(url.toString(), { signal }, auth);
        },
        async sendSignal(message, signal) {
            if (message.generation !== joined.controllerGeneration) throw new Error('The camera signal belongs to a different controller generation.');
            await cameraRequest(endpoints.signal, { method: 'POST', body: JSON.stringify({ kind: message.kind, generation: message.generation, payload: message.payload, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }), signal }, auth);
        },
        acknowledge(payload, signal) {
            return cameraRequest(endpoints.ack, { method: 'POST', body: JSON.stringify({ ...payload, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }), signal }, auth);
        },
        stage(file, payload, signal) {
            const form = new FormData();
            form.append('protocol_version', OMR_CAMERA_PROTOCOL_VERSION);
            form.append('frame', file);
            form.append('command_id', payload.commandId);
            form.append('frame_id', payload.frameId);
            form.append('target_key', payload.targetKey);
            if (payload.clientQuad) form.append('client_quad', JSON.stringify(payload.clientQuad));
            return cameraRequest(endpoints.frames, { method: 'POST', body: form, signal }, auth);
        },
        async heartbeat(payload, signal) {
            await cameraRequest(endpoints.heartbeat, { method: 'POST', body: JSON.stringify({ ...payload, protocol_version: OMR_CAMERA_PROTOCOL_VERSION }), signal }, auth);
        },
    };
}
