import { prepareZXingModule, readBarcodes, type ReadResult } from 'zxing-wasm/reader';
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

type DecodeRequest = Readonly<{ id: number; bitmap: ImageBitmap }>;
type DecodeResponse = Readonly<{
    id: number;
    observations?: Array<{ rawValue: string; bounds?: { x: number; y: number; width: number; height: number } }>;
    error?: string;
}>;

const MAX_DECODE_EDGE = 640;
let canvas: OffscreenCanvas | null = null;
let context: OffscreenCanvasRenderingContext2D | null = null;
let modulePrepared = false;

function positionBounds(result: ReadResult): { x: number; y: number; width: number; height: number } {
    const xValues = [result.position.topLeft.x, result.position.topRight.x, result.position.bottomLeft.x, result.position.bottomRight.x];
    const yValues = [result.position.topLeft.y, result.position.topRight.y, result.position.bottomLeft.y, result.position.bottomRight.y];
    const left = Math.min(...xValues);
    const top = Math.min(...yValues);
    return { x: left, y: top, width: Math.max(...xValues) - left, height: Math.max(...yValues) - top };
}

function ensureCanvas(width: number, height: number): OffscreenCanvasRenderingContext2D {
    if (!canvas || canvas.width !== width || canvas.height !== height) {
        canvas = new OffscreenCanvas(width, height);
        context = canvas.getContext('2d', { willReadFrequently: true });
    }
    if (!context) throw new Error('QR fallback could not create a bounded decode canvas.');
    return context;
}

async function decode({ id, bitmap }: DecodeRequest): Promise<void> {
    try {
        if (!modulePrepared) {
            prepareZXingModule({
                overrides: { locateFile: (path: string) => path.endsWith('.wasm') ? wasmUrl : path },
                equalityFn: (left, right) => left.locateFile === right.locateFile,
            });
            modulePrepared = true;
        }
        const scale = Math.min(1, MAX_DECODE_EDGE / Math.max(bitmap.width, bitmap.height));
        const width = Math.max(1, Math.round(bitmap.width * scale));
        const height = Math.max(1, Math.round(bitmap.height * scale));
        const frameContext = ensureCanvas(width, height);
        frameContext.drawImage(bitmap, 0, 0, width, height);
        const imageData = frameContext.getImageData(0, 0, width, height);
        const results = await readBarcodes(imageData, {
            formats: ['QRCode'],
            maxNumberOfSymbols: 4,
            isPure: false,
            returnErrors: true,
        });
        const observations = results
            .filter((result) => result.format === 'QRCode')
            .map((result) => ({ rawValue: result.text, bounds: positionBounds(result) }));
        self.postMessage({ id, observations } satisfies DecodeResponse);
    } catch (error) {
        self.postMessage({ id, error: error instanceof Error ? error.message : 'QR fallback decoding failed.' } satisfies DecodeResponse);
    } finally {
        bitmap.close();
    }
}

self.onmessage = (event: MessageEvent<DecodeRequest>) => {
    void decode(event.data);
};
