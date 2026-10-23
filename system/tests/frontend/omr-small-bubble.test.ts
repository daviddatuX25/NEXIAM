import { describe, expect, it } from 'vitest';
import {
    buildSolidItemFromOptionPoints,
    effectiveRecognitionGeometryMm,
    emitNativeMapping,
    minimumRecognitionPitchMm,
    resizeSolidItemBubbleDiameter,
    bubbleLabelLayout,
    MIN_STUDIO_SCALE,
    MAX_STUDIO_SCALE,
} from '../../resources/js/lib/omrStudioGeometry';
describe('uploaded OMR small-bubble geometry', () => {
    it('keeps the 1.26 mm edge gap at the uploaded and designed minimums', () => {
        expect(minimumRecognitionPitchMm(2.5, 2.5)).toBe(3.76);
        expect(minimumRecognitionPitchMm(3.0)).toBe(5.46);
    });

    it('uses the supplied minimum when resolving recognition geometry', () => {
        expect(
            effectiveRecognitionGeometryMm({
                kind: 'grid',
                bubbleDiameterMm: 3.0,
                minBubbleMm: 2.5,
            }).bubbleDiameterMm,
        ).toBe(3.0);
        expect(
            effectiveRecognitionGeometryMm({
                kind: 'grid',
                bubbleDiameterMm: 3.0,
            }).bubbleDiameterMm,
        ).toBe(4.2);
    });

    it('builds a 3 mm solid item with matching recognition dimensions', () => {
        const section = buildSolidItemFromOptionPoints({
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 60 },
                { x: 36, y: 60 },
                { x: 42, y: 60 },
                { x: 48, y: 60 },
            ],
            bubbleDiameterMm: 3.0,
            minBubbleMm: 2.5,
        });

        expect(section.bubbleDiameterMm).toBe(3.0);
        expect(section.h_mm).toBe(3.0);
        expect(section.w_mm).toBeGreaterThan(section.bubbleDiameterMm);
        expect(section.bubblePitchMm).toBeGreaterThanOrEqual(4.26);
    });

    it('emits the uploaded 3 mm diameter as 35 px without affecting default minimums', () => {
        const block = {
            id: 'Q1',
            type: 'mcq' as const,
            x_mm: 30,
            y_mm: 60,
            w_mm: 30,
            h_mm: 5,
            questions: 1,
            options: 4,
            bubble_diameter_mm: 3.0,
        };

        expect(
            emitNativeMapping(210, 297, [block], 1, { minBubbleMm: 2.5 })
                .blocks[0].bubble_width_px,
        ).toBe(35);
        expect(emitNativeMapping(210, 297, [block]).blocks[0].bubble_width_px).toBe(
            50,
        );
    });

    it('preserves the 2.5 mm minimum when resizing a solid item', () => {
        const section = buildSolidItemFromOptionPoints({
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 60 },
                { x: 36, y: 60 },
                { x: 42, y: 60 },
                { x: 48, y: 60 },
            ],
            bubbleDiameterMm: 3.0,
            minBubbleMm: 2.5,
        });

        expect(resizeSolidItemBubbleDiameter(section, 2.5, 2.5).bubbleDiameterMm).toBe(
            2.5,
        );
    });
    it('computes fitting font size for single characters and floats multi-character tokens in small bubbles', () => {
        const singleLarge = bubbleLabelLayout('A', 4.5);
        expect(singleLarge.showInside).toBe(true);
        expect(singleLarge.fontSizePx).toBeGreaterThanOrEqual(4.5);

        const singleSmall = bubbleLabelLayout('A', 2.5);
        expect(singleSmall.showInside).toBe(true);
        expect(singleSmall.fontSizePx).toBeLessThanOrEqual(singleLarge.fontSizePx);

        const pairedSmall = bubbleLabelLayout('A-B', 3.0);
        expect(pairedSmall.showInside).toBe(false);

        const pairedLarge = bubbleLabelLayout('A-B', 7.0);
        expect(pairedLarge.showInside).toBe(true);
    });

    it('enforces maximum studio scale of 6.0x (600%)', () => {
        expect(MAX_STUDIO_SCALE).toBe(6.0);
        expect(MIN_STUDIO_SCALE).toBe(0.35);
    });
});
