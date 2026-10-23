declare module 'qrcode' {
    export type QRCodeRenderOptions = {
        width?: number;
        margin?: number;
        errorCorrectionLevel?: 'L' | 'M' | 'Q' | 'H';
        color?: { dark?: string; light?: string };
    };

    export function toCanvas(canvas: HTMLCanvasElement, text: string, options?: QRCodeRenderOptions): Promise<HTMLCanvasElement>;

    const QRCode: { toCanvas: typeof toCanvas };
    export default QRCode;
}
