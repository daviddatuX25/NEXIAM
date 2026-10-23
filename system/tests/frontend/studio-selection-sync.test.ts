// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import StudioBubblePanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioBubblePanel.svelte';
import StudioCanvas from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioCanvas.svelte';
import {
    buildSolidItemFromOptionPoints,
    resizeSolidItemBubbleDiameter,
    rotateSolidItem,
    updateSolidItemOptionPoint,
} from '../../resources/js/lib/omrStudioGeometry';

afterEach(() => cleanup());

type GuideInput = {
    id: string;
    areaCode: string;
    itemNumber: number;
    subtestCode?: string;
    subtestId?: number | string;
    optionLabels?: string[];
    bubbleDiameterMm?: number;
    rotationDeg?: number;
};

function buildGuide({
    id,
    areaCode,
    itemNumber,
    subtestCode,
    subtestId,
    optionLabels = ['A-B', 'C-D'],
    bubbleDiameterMm = 5.8,
    rotationDeg = 1.2,
}: GuideInput) {
    return buildSolidItemFromOptionPoints({
        id,
        pageNo: 1,
        areaCode,
        subtest_code: subtestCode,
        test_instrument_subtest_id: subtestId,
        itemNumber,
        optionLabels,
        pointsMm: optionLabels.map((_, index) => ({ x: 30 + index * 6, y: 60 })),
        bubbleDiameterMm,
        rotationDeg,
    });
}

describe('Answer Sheet Studio selection property synchronization', () => {
    it('preserves subtest identity and rotation while building a solid guide', () => {
        const guide = buildGuide({
            id: 'vocabulary-q17',
            areaCode: 'LOGIC',
            itemNumber: 17,
            subtestCode: 'VOCABULARY',
            subtestId: '41',
            optionLabels: ['A', 'B', 'C', 'D'],
            rotationDeg: 2.4,
        });

        expect(guide).toMatchObject({
            subtest_code: 'VOCABULARY',
            test_instrument_subtest_id: 41,
            rotationDeg: 2.4,
        });
    });

    it('retains subtest identity through resize, option-point update, and rotation', () => {
        const guide = buildGuide({
            id: 'vocabulary-q17',
            areaCode: 'LOGIC',
            itemNumber: 17,
            subtestCode: 'VOCABULARY',
            subtestId: 41,
        });

        const updatedGuides = [
            resizeSolidItemBubbleDiameter(guide, 6.2),
            updateSolidItemOptionPoint(guide, 1, { x: 37, y: 61 }),
            rotateSolidItem(guide, 0.5),
        ];

        for (const updatedGuide of updatedGuides) {
            expect(updatedGuide).toMatchObject({
                subtest_code: 'VOCABULARY',
                test_instrument_subtest_id: 41,
            });
        }
    });

    it('hydrates the inspector from a selected guide and resolves instrument and legacy labels', async () => {
        const instrumentGuide = buildGuide({
            id: 'vocabulary-q17',
            areaCode: 'LOGIC',
            itemNumber: 17,
            subtestCode: 'VOCABULARY',
            subtestId: 41,
        });
        const legacyGuide = buildGuide({
            id: 'logic-q3',
            areaCode: 'LOGIC',
            itemNumber: 3,
            optionLabels: ['A', 'B', 'C', 'D'],
        });

        const canvas = render(StudioCanvas, {
            currentStage: 3,
            previewSurface: 'canonical',
            canonicalPreview: null,
            mappingPreviewStale: true,
            sections: [instrumentGuide, legacyGuide],
            components: [],
        });
        const guideLabels = Array.from(
            canvas.container.querySelectorAll('[data-omr-solid-item-badge="true"]'),
            (badge) => badge.textContent?.trim(),
        );
        expect(guideLabels).toEqual(['VOCABULARY·Q17', 'LOGIC·Q3']);
        canvas.unmount();

        const plottingConfig = {
            active: false,
            mode: 'consecutive',
            areaCode: 'COMPUTATION',
            subtest_code: 'COMPUTATION',
            test_instrument_subtest_id: 52,
            itemNumber: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
            bubbleDiameterMm: 4.5,
            currentOptionIndex: 0,
            pointsMm: [],
        };
        const panel = render(StudioBubblePanel, {
            props: {
                sections: [instrumentGuide],
                activeSectionIndex: -1,
                selectedSectionIndices: [],
                plottingConfig,
                testInstrument: {
                    subtests: [
                        { id: 41, code: 'VOCABULARY', name: 'Vocabulary' },
                        { id: 52, code: 'COMPUTATION', name: 'Computation' },
                    ],
                },
                aptitudeAreas: [],
                isUploadedSource: false,
                pageCount: 1,
            },
        });

        const tab = panel.container.querySelector('[role="tab"]') as HTMLButtonElement;
        expect(tab).not.toBeNull();
        await fireEvent.click(tab);

        await waitFor(() => {
            expect(
                (panel.container.querySelector(
                    '[data-omr-plotter-radius-slider="true"]',
                ) as HTMLInputElement).value,
            ).toBe('5.8');
            expect(
                (panel.container.querySelector(
                    '[data-omr-plotter-subtest-select="true"]',
                ) as HTMLSelectElement).value,
            ).toBe('VOCABULARY');
            expect(
                (panel.container.querySelector(
                    '[data-omr-custom-option-tokens="true"]',
                ) as HTMLInputElement).value,
            ).toBe('A-B, C-D');
        });
        expect(panel.container.textContent).toContain('Tilt Item (+1.2°):');
    });

    it('clears the active guide when the selected set is emptied without throwing', async () => {
        const guide = buildGuide({
            id: 'logic-q3',
            areaCode: 'LOGIC',
            itemNumber: 3,
            optionLabels: ['A', 'B', 'C', 'D'],
        });
        const canvas = render(StudioCanvas, {
            currentStage: 3,
            previewSurface: 'canonical',
            canonicalPreview: null,
            mappingPreviewStale: true,
            sections: [guide],
            components: [],
            activeSectionIndex: 0,
            selectedSectionIndices: [0],
            plottingConfig: { active: false },
        });
        const guideElement = canvas.container.querySelector(
            '[data-omr-section-id="logic-q3"]',
        ) as HTMLElement;
        expect(guideElement.className).toContain('bg-emerald-500/15');

        await fireEvent.keyDown(window, { key: 'Escape' });

        await waitFor(() => {
            expect(guideElement.className).not.toContain('bg-emerald-500/15');
        });
    });
});
