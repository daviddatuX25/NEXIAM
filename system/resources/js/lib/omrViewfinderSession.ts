import type {
    ViewfinderQuad,
    ViewfinderRequest,
    ViewfinderResult,
} from './omrViewfinderWorker';

export type ViewfinderCapture = {
    source: 'auto' | 'manual';
    quad: ViewfinderQuad | null;
};

type LockDurationMs = 150 | 300 | 500 | 800;

type ViewfinderSessionOptions = {
    video: () => HTMLVideoElement | null;
    onResult: (result: ViewfinderResult | null) => void;
    onUnavailable?: () => void;
    onCapture: (capture: ViewfinderCapture) => Promise<boolean>;
    onLockProgress?: (progress: number) => void;
    lockDurationMs?: LockDurationMs;
    expectedAspectRatio?: number;
    workerFactory?: () => Worker;
    now?: () => number;
};

const FRAME_INTERVAL_MS = 100;
const DEFAULT_LOCK_DURATION_MS: LockDurationMs = 300;
const ANALYSIS_EDGE = 320;

export function createOmrViewfinderSession(options: ViewfinderSessionOptions) {
    const createWorker =
        options.workerFactory ??
        (() =>
            new Worker(new URL('./omrViewfinderWorker.ts', import.meta.url), {
                type: 'module',
            }));
    const now = options.now ?? (() => performance.now());
    let worker: Worker | null = null;
    let timer: number | null = null;
    let generation = 0;
    let frameId = 0;
    let inFlight = false;
    let autoEnabled = false;
    let lockDurationMs = options.lockDurationMs ?? DEFAULT_LOCK_DURATION_MS;
    let lockedSince: number | null = null;
    let captureLatched = false;
    let capturePending = false;
    let latest: ViewfinderResult | null = null;

    function resetProgress(): void {
        options.onLockProgress?.(0);
    }

    function clearLock(): void {
        lockedSince = null;
        captureLatched = false;
        resetProgress();
    }

    function stop(): void {
        generation++;
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
        worker?.terminate();
        worker = null;
        inFlight = false;
        frameId = 0;
        latest = null;
        clearLock();
        options.onResult(null);
    }

    async function capture(
        source: ViewfinderCapture['source'],
    ): Promise<boolean> {
        if (capturePending || (source === 'auto' && captureLatched)) {
            return false;
        }
        // A synchronous latch prevents a queued worker result or double-click from encoding twice.
        captureLatched = true;
        capturePending = true;
        resetProgress();
        const captureGeneration = generation;
        const gates = latest?.gates;
        const quad =
            latest?.quad &&
            gates?.in_bounds &&
            gates.angle_ok &&
            gates.lighting_ok
                ? latest.quad
                : null;
        let succeeded = false;
        try {
            succeeded = await options.onCapture({ source, quad });
            return succeeded;
        } catch {
            return false;
        } finally {
            capturePending = false;
            if (!succeeded && captureGeneration === generation) {
                clearLock();
            }
        }
    }

    async function analyze(): Promise<void> {
        const video = options.video();
        if (
            !worker ||
            inFlight ||
            capturePending ||
            !video ||
            video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA ||
            !video.videoWidth ||
            !video.videoHeight
        ) {
            return;
        }
        inFlight = true;
        const activeGeneration = generation;
        const capturedAt = now();
        const scale = Math.min(
            1,
            ANALYSIS_EDGE / Math.max(video.videoWidth, video.videoHeight),
        );
        try {
            const bitmap = await createImageBitmap(video, {
                resizeWidth: Math.round(video.videoWidth * scale),
                resizeHeight: Math.round(video.videoHeight * scale),
                resizeQuality: 'medium',
            });
            if (!worker || activeGeneration !== generation) {
                bitmap.close();
                return;
            }
            const request: ViewfinderRequest = {
                type: 'analyze',
                bitmap,
                capturedAt,
                generation,
                frameId: ++frameId,
                expectedAspectRatio: options.expectedAspectRatio ?? 210 / 297,
            };
            try {
                worker.postMessage(request, [bitmap]);
            } catch (error) {
                bitmap.close();
                throw error;
            }
        } catch {
            if (activeGeneration === generation) {
                stop();
                options.onUnavailable?.();
            }
        }
    }

    function start(): boolean {
        stop();
        try {
            worker = createWorker();
        } catch {
            options.onUnavailable?.();
            return false;
        }
        const activeGeneration = generation;
        worker.onmessage = (event: MessageEvent<ViewfinderResult>) => {
            if (
                activeGeneration !== generation ||
                event.data.generation !== generation ||
                event.data.frameId !== frameId
            ) {
                return;
            }
            inFlight = false;
            latest = event.data;
            options.onResult(latest);
            const gates = latest.gates;
            const locked =
                latest.quad !== null &&
                gates.in_bounds &&
                gates.angle_ok &&
                gates.steady_ok &&
                gates.lighting_ok;
            if (!locked) {
                resetProgress();
                if (!capturePending) {
                    clearLock();
                }
                return;
            }
            if (!autoEnabled || capturePending || captureLatched) {
                return;
            }
            const observedAt = now();
            if (lockedSince === null) {
                lockedSince = observedAt;
            }
            const elapsed = Math.max(0, observedAt - lockedSince);
            const progress = Math.min(1, elapsed / lockDurationMs);
            options.onLockProgress?.(progress);
            if (elapsed >= lockDurationMs) {
                void capture('auto');
            }
        };
        worker.onerror = () => {
            if (activeGeneration === generation) {
                stop();
                options.onUnavailable?.();
            }
        };
        timer = window.setInterval(() => void analyze(), FRAME_INTERVAL_MS);
        void analyze();
        return true;
    }

    return {
        start,
        stop,
        analyze,
        captureManual: () => capture('manual'),
        setAutoEnabled(enabled: boolean) {
            if (autoEnabled !== enabled) {
                autoEnabled = enabled;
                clearLock();
            }
        },
        setLockDurationMs(ms: LockDurationMs) {
            if (![150, 300, 500, 800].includes(ms)) {
                return;
            }
            if (lockDurationMs !== ms) {
                lockDurationMs = ms;
                clearLock();
            }
        },
        get generation() {
            return generation;
        },
    };
}

