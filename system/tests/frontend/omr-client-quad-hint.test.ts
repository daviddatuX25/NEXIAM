// @vitest-environment jsdom
import { webcrypto } from 'node:crypto';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    appendOmrClientQuadHint,
    createOmrClientQuadHint,
} from '../../resources/js/lib/omrClientQuadHint';
import type { ViewfinderQuad } from '../../resources/js/lib/omrViewfinderWorker';

const quad: ViewfinderQuad = [
    { x: 0.1, y: 0.15 },
    { x: 0.9, y: 0.15 },
    { x: 0.9, y: 0.85 },
    { x: 0.1, y: 0.85 },
];

afterEach(() => vi.unstubAllGlobals());

describe('document-bound camera advisory', () => {
    it('binds the full uploaded bytes and page to the four ordered source-space corners', async () => {
        vi.stubGlobal('crypto', webcrypto);
        const bytes = new TextEncoder().encode('actual captured JPEG bytes');
        const file = { arrayBuffer: async () => bytes.buffer } as File;
        const hint = await createOmrClientQuadHint(file, quad, 1280, 960, 2);
        expect(hint).toEqual({
            points: [
                [0.1, 0.15],
                [0.9, 0.15],
                [0.9, 0.85],
                [0.1, 0.85],
            ],
            width: 1280,
            height: 960,
            orientation: 1,
            input_hash:
                'f15fd11b5e523a096d64d6ddf56344ebcf3dd6d98370cadab6e1e3eff6fbf422',
            page_no: 2,
        });
        const form = new FormData();
        appendOmrClientQuadHint(form, hint!);
        expect(Object.fromEntries(form.entries())).toMatchObject({
            'client_quad[points][0][0]': '0.1',
            'client_quad[points][3][1]': '0.85',
            'client_quad[width]': '1280',
            'client_quad[height]': '960',
            'client_quad[orientation]': '1',
            'client_quad[input_hash]': hint!.input_hash,
            'client_quad[page_no]': '2',
        });
    });

    it('does not send a hint if there is no polygon or no digest capability', async () => {
        const file = { arrayBuffer: vi.fn() } as unknown as File;
        expect(
            await createOmrClientQuadHint(file, null, 1280, 960, 1),
        ).toBeNull();
        expect(file.arrayBuffer).not.toHaveBeenCalled();
        vi.stubGlobal('crypto', undefined);
        expect(
            await createOmrClientQuadHint(file, quad, 1280, 960, 1),
        ).toBeNull();
    });
});
