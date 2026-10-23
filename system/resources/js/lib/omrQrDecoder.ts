export type OmrQrObservation = Readonly<{
    rawValue: string;
    bounds?: Readonly<{ x: number; y: number; width: number; height: number }>;
}>;

export type OmrQrDecoder = Readonly<{
    supported: boolean;
    detect(video: HTMLVideoElement): Promise<readonly OmrQrObservation[]>;
    dispose?(): void;
}>;

type NativeBarcode = Readonly<{ rawValue?: string; boundingBox?: DOMRectReadOnly }>;
type NativeDetector = Readonly<{ detect(video: HTMLVideoElement): Promise<NativeBarcode[]> }>;
type NativeDetectorConstructor = new (options: { formats: string[] }) => NativeDetector;
type DecoderWorkerResponse = Readonly<{ id: number; observations?: OmrQrObservation[]; error?: string }>;

function nativeQrDecoder(): OmrQrDecoder | null {
    const Constructor = (globalThis as typeof globalThis & { BarcodeDetector?: NativeDetectorConstructor }).BarcodeDetector;
    if (!Constructor) return null;
    try {
        const detector = new Constructor({ formats: ['qr_code'] });
        return {
            supported: true,
            async detect(video) {
                const detections = await detector.detect(video);
                return detections.map((detection) => ({
                    rawValue: detection.rawValue ?? '',
                    ...(detection.boundingBox ? { bounds: {
                        x: detection.boundingBox.x,
                        y: detection.boundingBox.y,
                        width: detection.boundingBox.width,
                        height: detection.boundingBox.height,
                    } } : {}),
                }));
            },
        };
    } catch {
        return null;
    }
}

function workerQrDecoder(): OmrQrDecoder {
    if (typeof Worker === 'undefined' || typeof createImageBitmap !== 'function') {
        return { supported: false, async detect() { throw new Error('QR fallback decoder is unavailable.'); } };
    }

    let worker: Worker;
    try {
        worker = new Worker(new URL('./omrQrDecoderWorker.ts', import.meta.url), { type: 'module', name: 'omr-qr-reader' });
    } catch {
        return { supported: false, async detect() { throw new Error('QR fallback decoder could not start.'); } };
    }

    let nextId = 0;
    const pending = new Map<number, { resolve(observations: readonly OmrQrObservation[]): void; reject(error: Error): void }>();
    worker.onmessage = (event: MessageEvent<DecoderWorkerResponse>) => {
        const response = event.data;
        const waiter = pending.get(response.id);
        if (!waiter) return;
        pending.delete(response.id);
        if (response.error) waiter.reject(new Error(response.error));
        else waiter.resolve(response.observations ?? []);
    };
    worker.onerror = () => {
        for (const waiter of pending.values()) waiter.reject(new Error('QR fallback decoder worker failed.'));
        pending.clear();
    };

    return {
        supported: true,
        async detect(video) {
            const scale = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
            const bitmap = await createImageBitmap(video, {
                resizeWidth: Math.max(1, Math.round(video.videoWidth * scale)),
                resizeHeight: Math.max(1, Math.round(video.videoHeight * scale)),
                resizeQuality: 'low',
            });
            const id = ++nextId;
            return new Promise<readonly OmrQrObservation[]>((resolve, reject) => {
                pending.set(id, { resolve, reject });
                try {
                    worker.postMessage({ id, bitmap }, [bitmap]);
                } catch (error) {
                    pending.delete(id);
                    bitmap.close();
                    reject(error instanceof Error ? error : new Error('QR fallback frame transfer failed.'));
                }
            });
        },
        dispose() {
            worker.terminate();
            for (const waiter of pending.values()) waiter.reject(new Error('QR decoder disposed.'));
            pending.clear();
        },
    };
}

export function createOmrQrDecoder(): OmrQrDecoder {
    return nativeQrDecoder() ?? workerQrDecoder();
}
