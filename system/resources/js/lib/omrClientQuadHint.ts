import type { ViewfinderQuad } from './omrViewfinderWorker';

export type OmrClientQuadHint = {
    points: [number, number][];
    width: number;
    height: number;
    orientation: 1;
    input_hash: string;
    page_no: number;
};

export async function createOmrClientQuadHint(
    file: File,
    quad: ViewfinderQuad | null,
    width: number,
    height: number,
    pageNo: number,
): Promise<OmrClientQuadHint | null> {
    if (
        !quad ||
        !Number.isInteger(width) ||
        !Number.isInteger(height) ||
        width < 1 ||
        height < 1 ||
        !Number.isInteger(pageNo) ||
        pageNo < 1 ||
        pageNo > 4 ||
        !globalThis.crypto?.subtle
    ) {
        return null;
    }
    try {
        const digest = await crypto.subtle.digest(
            'SHA-256',
            await file.arrayBuffer(),
        );
        const inputHash = Array.from(new Uint8Array(digest), (byte) =>
            byte.toString(16).padStart(2, '0'),
        ).join('');
        return {
            points: quad.map(({ x, y }) => [x, y]),
            width,
            height,
            orientation: 1,
            input_hash: inputHash,
            page_no: pageNo,
        };
    } catch {
        // Camera capture remains usable without an advisory hint.
        return null;
    }
}

export function appendOmrClientQuadHint(
    form: FormData,
    hint: OmrClientQuadHint,
): void {
    hint.points.forEach(([x, y], index) => {
        form.append(`client_quad[points][${index}][0]`, String(x));
        form.append(`client_quad[points][${index}][1]`, String(y));
    });
    form.append('client_quad[width]', String(hint.width));
    form.append('client_quad[height]', String(hint.height));
    form.append('client_quad[orientation]', String(hint.orientation));
    form.append('client_quad[input_hash]', hint.input_hash);
    form.append('client_quad[page_no]', String(hint.page_no));
}
