// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createOmrViewfinderSession } from '../../resources/js/lib/omrViewfinderSession';
import type {
    ViewfinderRequest,
    ViewfinderResult,
} from '../../resources/js/lib/omrViewfinderWorker';

const quad = [
    { x: 0.2, y: 0.2 },
    { x: 0.8, y: 0.2 },
    { x: 0.8, y: 0.8 },
    { x: 0.2, y: 0.8 },
] as const;

class FakeWorker {
    onmessage: ((event: MessageEvent<ViewfinderResult>) => void) | null = null;
    onerror: (() => void) | null = null;
    requests: ViewfinderRequest[] = [];
    terminated = false;

    postMessage(request: ViewfinderRequest): void {
        this.requests.push(request);
    }

    terminate(): void {
        this.terminated = true;
    }

    reply(locked: boolean, request = this.requests.at(-1)): void {
        if (!request) throw new Error('No analysis request');
        this.onmessage?.({
            data: {
                type: 'analysis',
                generation: request.generation,
                frameId: request.frameId,
                quad: [...quad],
                gates: {
                    in_bounds: true,
                    angle_ok: true,
                    steady_ok: locked,
                    lighting_ok: true,
                },
            },
        } as MessageEvent<ViewfinderResult>);
    }
}

let timestamp = 0;
let video: HTMLVideoElement;
let workers: FakeWorker[];
let bitmapCalls: ReturnType<typeof vi.fn>;

beforeEach(() => {
    vi.useFakeTimers();
    timestamp = 0;
    workers = [];
    video = document.createElement('video');
    Object.defineProperties(video, {
        readyState: { configurable: true, value: 0 },
        videoWidth: { configurable: true, value: 1280 },
        videoHeight: { configurable: true, value: 960 },
    });
    bitmapCalls = vi.fn(async () => ({ close: vi.fn() }));
    vi.stubGlobal('createImageBitmap', bitmapCalls);
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

function session(onCapture = vi.fn(async () => true)) {
    const results: Array<ViewfinderResult | null> = [];
    const controller = createOmrViewfinderSession({
        video: () => video,
        onResult: (result) => results.push(result),
        onCapture,
        now: () => timestamp,
        workerFactory: () => {
            const worker = new FakeWorker();
            workers.push(worker);
            return worker as unknown as Worker;
        },
    });
    return { controller, results, onCapture };
}

async function sendFrame(
    controller: ReturnType<typeof createOmrViewfinderSession>,
    locked: boolean,
): Promise<void> {
    await controller.analyze();
    workers.at(-1)?.reply(locked);
}

describe('OMR live viewfinder lifecycle', () => {
    it('downscales one frame at a time and discards old-generation replies', async () => {
        const { controller, results } = session();
        controller.start();
        Object.defineProperty(video, 'readyState', {
            configurable: true,
            value: 2,
        });
        await controller.analyze();
        const first = workers[0];
        expect(first.requests).toHaveLength(1);
        expect(bitmapCalls).toHaveBeenCalledWith(
            video,
            expect.objectContaining({ resizeWidth: 320, resizeHeight: 240 }),
        );
        await controller.analyze();
        expect(first.requests).toHaveLength(1);

        controller.stop();
        expect(first.terminated).toBe(true);
        const afterStop = results.length;
        first.reply(true);
        expect(results).toHaveLength(afterStop);
        controller.start();
        await controller.analyze();
        const second = workers[1];
        expect(second.requests[0].generation).toBeGreaterThan(
            first.requests[0].generation,
        );
        second.reply(false);
        expect(results.at(-1)?.gates.steady_ok).toBe(false);
        controller.stop();
    });

    it('requires 300 ms uninterrupted lock, latches before encoding, and retries only after failure', async () => {
        let finish!: (succeeded: boolean) => void;
        const onCapture = vi.fn(
            () =>
                new Promise<boolean>((resolve) => {
                    finish = resolve;
                }),
        );
        const { controller } = session(onCapture);
        controller.start();
        controller.setAutoEnabled(true);
        Object.defineProperty(video, 'readyState', {
            configurable: true,
            value: 2,
        });
        await sendFrame(controller, false);
        timestamp = 100;
        await sendFrame(controller, true);
        timestamp = 250;
        await sendFrame(controller, true);
        expect(onCapture).not.toHaveBeenCalled();
        timestamp = 410;
        await sendFrame(controller, true);
        expect(onCapture).toHaveBeenCalledTimes(1);
        expect(onCapture).toHaveBeenCalledWith({
            source: 'auto',
            quad: [...quad],
        });
        expect(await controller.captureManual()).toBe(false);
        expect(onCapture).toHaveBeenCalledTimes(1);
        finish(false);
        await Promise.resolve();
        timestamp = 500;
        await sendFrame(controller, true);
        timestamp = 810;
        await sendFrame(controller, true);
        expect(onCapture).toHaveBeenCalledTimes(2);
        finish(true);
        await Promise.resolve();
        timestamp = 900;
        await sendFrame(controller, true);
        expect(onCapture).toHaveBeenCalledTimes(2);
        await sendFrame(controller, false);
        timestamp = 1000;
        await sendFrame(controller, true);
        timestamp = 1310;
        await sendFrame(controller, true);
        expect(onCapture).toHaveBeenCalledTimes(3);
        finish(true);
        controller.stop();
    });

    it('cancels a lock on page/stream restart and allows manual capture without a Worker', async () => {
        const { controller, onCapture } = session();
        controller.start();
        controller.setAutoEnabled(true);
        Object.defineProperty(video, 'readyState', {
            configurable: true,
            value: 2,
        });
        await sendFrame(controller, true);
        timestamp = 200;
        controller.start();
        expect(workers[0].terminated).toBe(true);
        await sendFrame(controller, true);
        timestamp = 400;
        await sendFrame(controller, true);
        expect(onCapture).not.toHaveBeenCalled();
        controller.stop();
        expect(await controller.captureManual()).toBe(true);
        expect(onCapture).toHaveBeenCalledWith({
            source: 'manual',
            quad: null,
        });
    });
    it('keeps manual capture usable when the analysis Worker cannot start', async () => {
        const onCapture = vi.fn(async () => true);
        const onUnavailable = vi.fn();
        const controller = createOmrViewfinderSession({
            video: () => video,
            onResult: vi.fn(),
            onUnavailable,
            onCapture,
            workerFactory: () => {
                throw new Error('Worker unavailable');
            },
        });
        expect(controller.start()).toBe(false);
        expect(onUnavailable).toHaveBeenCalledOnce();
        expect(await controller.captureManual()).toBe(true);
        expect(onCapture).toHaveBeenCalledWith({ source: 'manual', quad: null });
    });
    it('closes an encoded frame after visibility or device teardown before worker delivery', async () => {
        let finishBitmap!: (bitmap: ImageBitmap) => void;
        const close = vi.fn();
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(() => new Promise<ImageBitmap>((resolve) => {
                finishBitmap = resolve;
            })),
        );
        Object.defineProperty(video, 'readyState', { configurable: true, value: 2 });
        const { controller } = session();
        controller.start();
        controller.stop();
        finishBitmap({ close } as unknown as ImageBitmap);
        await Promise.resolve();
        expect(close).toHaveBeenCalledOnce();
        expect(workers[0].requests).toHaveLength(0);
    });

    it('releases the shutter latch after an encoding or upload rejection', async () => {
        const onCapture = vi.fn()
            .mockRejectedValueOnce(new Error('encoding failed'))
            .mockResolvedValueOnce(true);
        const { controller } = session(onCapture);
        expect(await controller.captureManual()).toBe(false);
        expect(await controller.captureManual()).toBe(true);
        expect(onCapture).toHaveBeenCalledTimes(2);
    });
});
