import { describe, expect, it } from 'vitest';
import {
    analyzeViewfinderImage,
    viewfinderFramingGuidance,
    type ViewfinderPoint,
    type ViewfinderQuad,
} from '../../resources/js/lib/omrViewfinderWorker';

const width = 320;
const height = 240;
const paper: ViewfinderQuad = [
    { x: 104 / width, y: 40 / height },
    { x: 216 / width, y: 40 / height },
    { x: 216 / width, y: 200 / height },
    { x: 104 / width, y: 200 / height },
];

function paperFrame(
    quads: ViewfinderQuad[],
    paperLightness: (x: number, y: number) => number = () => 220,
): ImageData {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const lightness = quads.some((quad) => {
                let inside = false;
                for (let i = 0, j = 3; i < 4; j = i++) {
                    const left = quad[i];
                    const right = quad[j];
                    const crosses =
                        left.y > y / height !== right.y > y / height &&
                        x / width <
                            ((right.x - left.x) * (y / height - left.y)) /
                                (right.y - left.y) +
                                left.x;
                    if (crosses) inside = !inside;
                }
                return inside;
            })
                ? paperLightness(x, y)
                : 35;
            const offset = (y * width + x) * 4;
            data[offset] = lightness;
            data[offset + 1] = lightness;
            data[offset + 2] = lightness;
            data[offset + 3] = 255;
        }
    }
    return { width, height, data } as ImageData;
}

function shift(quad: ViewfinderQuad, x: number, y: number): ViewfinderQuad {
    return quad.map((point: ViewfinderPoint) => ({
        x: point.x + x / width,
        y: point.y + y / height,
    })) as ViewfinderQuad;
}

describe('local OMR paper viewfinder analysis', () => {
    it('orders a detected quad TL, TR, BR, BL and requires a prior stable frame', () => {
        const first = analyzeViewfinderImage(paperFrame([paper]), 0.7);
        expect(first.quad).not.toBeNull();
        expect(first.quad![0].x).toBeLessThan(first.quad![1].x);
        expect(first.quad![0].y).toBeLessThan(first.quad![3].y);
        expect(first.quad![2].x).toBeGreaterThan(first.quad![3].x);
        expect(first.gates).toEqual({
            in_bounds: true,
            angle_ok: true,
            steady_ok: false,
            lighting_ok: true,
        });
        expect(
            analyzeViewfinderImage(paperFrame([paper]), 0.7, first.quad).gates
                .steady_ok,
        ).toBe(true);
        expect(
            analyzeViewfinderImage(
                paperFrame([shift(paper, 20, 0)]),
                0.7,
                first.quad,
            ).gates.steady_ok,
        ).toBe(false);
    });

    it('rejects near-border and skewed paper without suppressing its advisory quad', () => {
        const edge = analyzeViewfinderImage(
            paperFrame([shift(paper, 0, -38)]),
            0.7,
        );
        expect(edge.quad).not.toBeNull();
        expect(edge.gates.in_bounds).toBe(false);
        const tilted: ViewfinderQuad = [
            { x: 90 / width, y: 45 / height },
            { x: 205 / width, y: 73 / height },
            { x: 230 / width, y: 197 / height },
            { x: 115 / width, y: 169 / height },
        ];
        const result = analyzeViewfinderImage(paperFrame([tilted]), 0.7);
        expect(result.quad).not.toBeNull();
        expect(result.gates.angle_ok).toBe(false);
    });

    it('advises rotation only when the outline fits the reciprocal ratio without excess tilt', () => {
        const landscape: ViewfinderQuad = [
            { x: 80 / width, y: 64 / height },
            { x: 240 / width, y: 64 / height },
            { x: 240 / width, y: 176 / height },
            { x: 80 / width, y: 176 / height },
        ];
        const result = analyzeViewfinderImage(paperFrame([landscape]), 0.7);

        expect(result.gates.angle_ok).toBe(false);
        expect(result.geometry?.observedAspectRatio).toBeCloseTo(160 / 112, 1);
        expect(viewfinderFramingGuidance(result)).toContain(
            'Rotate the sheet to portrait; its detected outline matches the opposite orientation.',
        );

        const tilted: ViewfinderQuad = [
            { x: 70 / width, y: 55 / height },
            { x: 230 / width, y: 105 / height },
            { x: 250 / width, y: 180 / height },
            { x: 90 / width, y: 155 / height },
        ];
        const tiltedResult = analyzeViewfinderImage(paperFrame([tilted]), 0.7);
        expect(tiltedResult.gates.angle_ok).toBe(false);
        expect(viewfinderFramingGuidance(tiltedResult)).toContain(
            'Hold the camera square over the sheet to reduce tilt or perspective.',
        );
        expect(viewfinderFramingGuidance(tiltedResult)).not.toContain(
            'Rotate the sheet to portrait; its detected outline matches the opposite orientation.',
        );
    });

    it('fails closed on poor lighting, absent boundaries, and competing paper regions', () => {
        expect(
            analyzeViewfinderImage(
                paperFrame([paper], () => 110),
                0.7,
            ).gates.lighting_ok,
        ).toBe(false);
        expect(
            analyzeViewfinderImage(
                paperFrame([paper], () => 255),
                0.7,
            ).gates.lighting_ok,
        ).toBe(false);
        expect(analyzeViewfinderImage(paperFrame([]), 0.7).quad).toBeNull();
        const left = shift(paper, -70, 0);
        const right = shift(paper, 70, 0);
        expect(
            analyzeViewfinderImage(paperFrame([left, right]), 0.7).quad,
        ).toBeNull();
    });
});
