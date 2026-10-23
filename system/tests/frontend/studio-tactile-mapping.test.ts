// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';
import StudioBubblePanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioBubblePanel.svelte';
import StudioCanvas from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioCanvas.svelte';
import {
    assessUploadedMappingReadiness,
    buildApplicantDigitRollFromPoints,
    buildSolidItemFromOptionPoints,
    cloneSolidItemAtAnchor,
    detectAdjacentVerticalStepMm,
    detectStaticPaperCornersFromPixels,
    interpolateTenDigitPointsFromSpan,
    multiplyItemsDownward,
    nextItemNumberForArea,
    resetPhotoCropCorners,
    resizeSolidItemBubbleDiameter,
    rotateGroupOfItems,
    rotatePhotoCropCornersClockwise,
    rotateSolidItem,
    solveAutoGridFromTwoPoints,
    syncDeclaredInventoryFromSections,
    updateSolidItemOptionPoint,
} from '../../resources/js/lib/omrStudioGeometry';
describe('Studio tactile mapping & underlay stability', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('renders permanent immutable underlay for uploaded documents across canvas clicks', async () => {
        const testRasterUrl = '/assets/test-uploaded-sheet.jpg';
        const onClearMock = vi.fn();

        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: testRasterUrl,
            currentStage: 3,
            activePageNo: 1,
            sections: [
                {
                    id: 'sec_1',
                    type: 'mcq',
                    posX: 25,
                    posY: 70,
                    bubbleDiameterMm: 4.5,
                    bubblePitchMm: 5.8,
                    rowPitchMm: 6.8,
                    questionsCount: 20,
                    optionsCount: 4,
                    page_no: 1,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
            onCanvasSelectionClear: onClearMock,
        });

        // The uploaded sheet image should be rendered with z-0 class
        const underlay = container.querySelector(
            `img[src="${testRasterUrl}"]`,
        ) as HTMLImageElement | null;
        expect(underlay).not.toBeNull();
        expect(underlay?.alt).toContain('Uploaded source sheet');

        // Clicking on the canvas should NOT remove or hide the underlay
        const viewport = container.querySelector('[role="region"]');
        expect(viewport).not.toBeNull();
        if (viewport) {
            await fireEvent.mouseDown(viewport);
            await fireEvent.mouseUp(viewport);
        }

        const underlayAfterClick = container.querySelector(
            `img[src="${testRasterUrl}"]`,
        );
        expect(underlayAfterClick).not.toBeNull();
    });

    it('renders direct-manipulation option pitch and row pitch handles when a section is selected in Stage 3', () => {
        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections: [
                {
                    id: 'sec_q1_20',
                    type: 'mcq',
                    posX: 30,
                    posY: 80,
                    bubbleDiameterMm: 4.5,
                    bubblePitchMm: 5.5,
                    rowPitchMm: 6.5,
                    questionsCount: 15,
                    optionsCount: 4,
                    page_no: 1,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
        });

        const bubblePitchHandle = container.querySelector(
            '[data-omr-pitch-handle="bubblePitch"]',
        );
        const rowPitchHandle = container.querySelector(
            '[data-omr-pitch-handle="rowPitch"]',
        );

        expect(bubblePitchHandle).not.toBeNull();
        expect(rowPitchHandle).not.toBeNull();
        expect(bubblePitchHandle?.textContent).toContain('5.5mm');
        expect(rowPitchHandle?.textContent).toContain('6.5mm');
    });

    it('clears a single Stage 3 section selection on Escape', async () => {
        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections: [
                {
                    id: 'escape_q1',
                    type: 'mcq',
                    posX: 30,
                    posY: 80,
                    bubbleDiameterMm: 4.5,
                    bubblePitchMm: 5.8,
                    rowPitchMm: 6.8,
                    questionsCount: 1,
                    optionsCount: 4,
                    page_no: 1,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
            selectedSectionIndices: [0],
        });

        const section = container.querySelector(
            '[data-omr-section-id="escape_q1"]',
        ) as HTMLElement;
        expect(section.className).toContain('bg-emerald-500/15');

        await fireEvent.keyDown(window, { key: 'Escape' });

        expect(section.className).not.toContain('bg-emerald-500/15');
    });

    it('clears all Stage 3 section selections on Escape', async () => {
        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections: [80, 100].map((posY, index) => ({
                id: `escape_q${index + 1}`,
                type: 'mcq',
                posX: 30,
                posY,
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 5.8,
                rowPitchMm: 6.8,
                questionsCount: 1,
                optionsCount: 4,
                page_no: 1,
                isLocked: false,
            })),
            activeSectionIndex: 1,
            selectedSectionIndices: [0, 1],
        });

        const selectedSections = container.querySelectorAll(
            '[data-omr-section-id]',
        );
        expect(selectedSections).toHaveLength(2);
        expect(
            Array.from(selectedSections).every((section) =>
                section.className.includes('bg-emerald-500/15'),
            ),
        ).toBe(true);

        await fireEvent.keyDown(window, { key: 'Escape' });

        expect(
            Array.from(selectedSections).every(
                (section) => !section.className.includes('bg-emerald-500/15'),
            ),
        ).toBe(true);
    });

    it('requires a fresh Escape after cancelling plotting before deselecting', async () => {
        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections: [
                {
                    id: 'escape_plot_q1',
                    type: 'mcq',
                    posX: 30,
                    posY: 80,
                    bubbleDiameterMm: 4.5,
                    bubblePitchMm: 5.8,
                    rowPitchMm: 6.8,
                    questionsCount: 1,
                    optionsCount: 4,
                    page_no: 1,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
            selectedSectionIndices: [0],
            plottingConfig: {
                active: true,
                mode: 'consecutive',
                areaCode: 'LOGIC',
                itemNumber: 2,
                optionsCount: 4,
                optionLabels: ['A', 'B', 'C', 'D'],
                bubbleDiameterMm: 4.5,
                currentOptionIndex: 0,
                pointsMm: [],
            },
        });

        const section = container.querySelector(
            '[data-omr-section-id="escape_plot_q1"]',
        ) as HTMLElement;
        expect(container.querySelector('[data-omr-plotting-banner]')).not.toBeNull();

        await fireEvent.keyDown(window, { key: 'Escape' });
        expect(container.querySelector('[data-omr-plotting-banner]')).toBeNull();
        expect(section.className).toContain('bg-emerald-500/15');

        await fireEvent.keyDown(window, { key: 'Escape', repeat: true });
        expect(section.className).toContain('bg-emerald-500/15');

        await fireEvent.keyDown(window, { key: 'Escape' });
        expect(section.className).not.toContain('bg-emerald-500/15');
    });

    it('clears section selection from the aptitude selector but not while editing text', async () => {
        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections: [
                {
                    id: 'escape_control_q1',
                    type: 'mcq',
                    posX: 30,
                    posY: 80,
                    bubbleDiameterMm: 4.5,
                    bubblePitchMm: 5.8,
                    rowPitchMm: 6.8,
                    questionsCount: 1,
                    optionsCount: 4,
                    page_no: 1,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
            selectedSectionIndices: [0],
        });

        const section = container.querySelector(
            '[data-omr-section-id="escape_control_q1"]',
        ) as HTMLElement;
        const textInput = document.createElement('input');
        const aptitudeSelector = document.createElement('select');
        document.body.append(textInput, aptitudeSelector);

        try {
            textInput.focus();

            await fireEvent.keyDown(window, { key: 'Escape' });

            expect(section.className).toContain('bg-emerald-500/15');

            aptitudeSelector.focus();

            await fireEvent.keyDown(window, { key: 'Escape' });

            expect(section.className).not.toContain('bg-emerald-500/15');
        } finally {
            textInput.remove();
            aptitudeSelector.remove();
        }
    });

    it('hides the item inspector when there is no active section', () => {
        const { container } = render(StudioBubblePanel, {
            props: {
                sections: [
                    buildSolidItemFromOptionPoints({
                        id: 'unselected_q1',
                        pageNo: 1,
                        areaCode: 'LOGIC',
                        itemNumber: 1,
                        optionLabels: ['A', 'B', 'C', 'D'],
                        pointsMm: [
                            { x: 30, y: 50 },
                            { x: 36, y: 50 },
                            { x: 42, y: 50 },
                            { x: 48, y: 50 },
                        ],
                        bubbleDiameterMm: 4.5,
                    }),
                ],
                activeSectionIndex: -1,
                selectedSectionIndices: [],
                plottingConfig: {
                    active: false,
                    mode: 'consecutive',
                    areaCode: 'VERBAL',
                    itemNumber: 1,
                    optionsCount: 4,
                    optionLabels: ['A', 'B', 'C', 'D'],
                    bubbleDiameterMm: 4.5,
                    currentOptionIndex: 0,
                    pointsMm: [],
                },
                isUploadedSource: true,
                pageCount: 1,
            },
        });

        expect(
            container.querySelector('[data-omr-active-item-area]'),
        ).toBeNull();
        expect(
            container.querySelector('[data-omr-active-item-number]'),
        ).toBeNull();
        expect(container.querySelector('[data-omr-panel-delete]')).toBeNull();
    });

    it('adjusts position and pitch via sub-millimeter keyboard arrow navigation', async () => {
        const onGeometryChanged = vi.fn();
        const section = {
            id: 'sec_nav',
            type: 'mcq',
            posX: 30.0,
            posY: 80.0,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 5.5,
            rowPitchMm: 6.5,
            questionsCount: 10,
            optionsCount: 4,
            page_no: 1,
            isLocked: false,
        };

        render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections: [section],
            activeSectionIndex: 0,
            onGeometryChanged,
        });

        // Micro-nudge right by 0.2mm
        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowRight' }),
        );
        expect(section.posX).toBe(30.2);
        expect(onGeometryChanged).toHaveBeenCalled();

        // Shift jump down by 1.0mm
        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowDown', shiftKey: true }),
        );
        expect(section.posY).toBe(81.0);

        // Alt + ArrowRight tunes option pitch by +0.1mm
        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true }),
        );
        expect(section.bubblePitchMm).toBe(5.6);

        // Alt + ArrowUp tunes row pitch by -0.1mm
        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowUp', altKey: true }),
        );
        expect(section.rowPitchMm).toBe(6.4);
    });

    it('solves 2-click diagonal auto-grid on canvas and rejects inverted points (Ticket 05)', async () => {
        // Pure solver verification: 4 options (3 gaps of 6.0mm = 18.0mm), 10 rows (9 gaps of 6.8mm = 61.2mm)
        // First bubble center at (40.0, 60.0), last bubble center at (58.0, 121.2)
        const solved = solveAutoGridFromTwoPoints({
            kind: 'grid',
            firstPointMm: { x: 40.0, y: 60.0 },
            lastPointMm: { x: 58.0, y: 121.2 },
            columns: 4,
            rows: 10,
            bubbleDiameterMm: 4.5,
        });
        expect(solved.ok).toBe(true);
        expect(solved.bubblePitchMm).toBeCloseTo(6.0, 1);
        expect(solved.rowPitchMm).toBeCloseTo(6.8, 1);
        // First bubble top-left = 40.0 - 2.25 = 37.75mm; minus grid offset 5.0mm => posX ~ 32.75mm
        expect(solved.posX).toBeCloseTo(32.75, 0);
        expect(solved.posY).toBeCloseTo(49.91, 1);

        // Degenerate/inverted points are rejected cleanly
        const inverted = solveAutoGridFromTwoPoints({
            kind: 'grid',
            firstPointMm: { x: 80.0, y: 120.0 },
            lastPointMm: { x: 40.0, y: 60.0 },
            columns: 4,
            rows: 10,
        });
        expect(inverted.ok).toBe(false);
        expect(inverted.error).toContain('Second point');

        // Interactive 2-click canvas test
        const onGeometryChanged = vi.fn();
        const section = {
            id: 'sec_autogrid',
            type: 'mcq',
            posX: 15.0,
            posY: 40.0,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 6.0,
            rowPitchMm: 6.8,
            questionsCount: 10,
            optionsCount: 4,
            page_no: 1,
            isLocked: false,
        };

        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            scale: 1.0,
            panX: 0,
            panY: 0,
            sections: [section],
            activeSectionIndex: 0,
            onGeometryChanged,
        });

        const trigger = container.querySelector(
            '[data-omr-auto-grid-trigger]',
        ) as HTMLButtonElement | null;
        expect(trigger).not.toBeNull();
        await fireEvent.click(trigger!);

        const banner = container.querySelector('[data-omr-auto-grid-banner]');
        expect(banner?.textContent).toContain('Step 1 of 2');

        const sheet = container.querySelector(
            '[data-omr-sheet="true"]',
        ) as HTMLElement;
        // Click 1: (40mm, 60mm) => clientX = 40 * 2.476 = 99.04, clientY = 60 * 2.476 = 148.56
        await fireEvent.mouseDown(sheet, { clientX: 99.04, clientY: 148.56 });
        expect(
            container.querySelector('[data-omr-auto-grid-point="1"]'),
        ).not.toBeNull();
        expect(banner?.textContent).toContain('Step 2 of 2');

        // Click 2: (58mm, 121.2mm) => clientX = 58 * 2.476 = 143.61, clientY = 121.2 * 2.476 = 300.09
        await fireEvent.mouseDown(sheet, { clientX: 143.61, clientY: 300.09 });

        expect(section.bubblePitchMm).toBeCloseTo(6.0, 1);
        expect(section.rowPitchMm).toBeCloseTo(6.8, 1);
        expect(section.posX).toBeCloseTo(32.75, 1);
        expect(onGeometryChanged).toHaveBeenCalled();
    });

    it('supports CamScanner corner loupe, 90° clockwise rotation, and sheet presets (Ticket 04)', async () => {
        // 1. Preset and clockwise rotation math
        const preset = resetPhotoCropCorners(0.02);
        expect(preset).toEqual([
            { x: 0.02, y: 0.02 },
            { x: 0.98, y: 0.02 },
            { x: 0.98, y: 0.98 },
            { x: 0.02, y: 0.98 },
        ]);

        const customCorners = [
            { x: 0.1, y: 0.2 }, // TL
            { x: 0.8, y: 0.2 }, // TR
            { x: 0.8, y: 0.9 }, // BR
            { x: 0.1, y: 0.9 }, // BL
        ];
        const rotated = rotatePhotoCropCornersClockwise(customCorners);
        // Old BL (0.1, 0.9) -> new TL (1 - 0.9, 0.1) = (0.1, 0.1)
        expect(rotated[0]).toEqual({ x: 0.1, y: 0.1 });
        // Old TL (0.1, 0.2) -> new TR (1 - 0.2, 0.1) = (0.8, 0.1)
        expect(rotated[1]).toEqual({ x: 0.8, y: 0.1 });

        // 2. Static paper detector on synthetic 100x100 RGBA buffer (dark desk = 40, bright sheet [15..85, 10..90] = 230)
        const width = 100;
        const height = 100;
        const rgba = new Uint8ClampedArray(width * height * 4);
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const idx = (y * width + x) * 4;
                const inside = x >= 15 && x <= 85 && y >= 10 && y <= 90;
                const val = inside ? 230 : 40;
                rgba[idx] = val;
                rgba[idx + 1] = val;
                rgba[idx + 2] = val;
                rgba[idx + 3] = 255;
            }
        }
        const detected = detectStaticPaperCornersFromPixels(rgba, width, height);
        expect(detected).not.toBeNull();
        expect(detected![0].x).toBeCloseTo(0.155, 1);
        expect(detected![0].y).toBeCloseTo(0.105, 1);
        expect(detected![2].x).toBeCloseTo(0.855, 1);
        expect(detected![2].y).toBeCloseTo(0.905, 1);

        // 3. Crop modal UI: loupe mounting, Reset to Sheet, and Rotate 90°
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                json: async () => ({ lock_version: 2 }),
            })),
        );
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(async () => ({
                width: 1200,
                height: 1600,
                close: vi.fn(),
            })),
        );
        if (!URL.createObjectURL) {
            URL.createObjectURL = vi.fn(() => 'blob:mock-sheet');
        } else {
            vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock-sheet');
        }
        if (!URL.revokeObjectURL) {
            URL.revokeObjectURL = vi.fn();
        }

        const { container } = render(OmrTemplateStudio, {
            draft: {
                id: 99,
                can_edit: true,
                lock_version: 1,
                registration_mode: 'document_bound',
                qr_mode: 'qr_disabled',
                source_kind: 'image',
                requested_page_count: 1,
                stage: 'draft',
                authoring: {
                    studio: {
                        current_stage: 1,
                        stage1Mode: 'upload',
                        baseType: 'image',
                    },
                },
            },
            marker_profiles: [],
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/99',
                source_ingest: '/setup/omr/studio/99/ingest',
                source_rectify: '/setup/omr/studio/99/rectify',
            },
        });

        const fileInput = container.querySelector(
            'input[type="file"]',
        ) as HTMLInputElement;
        expect(fileInput).not.toBeNull();

        const mockFile = new File(['fake-image'], 'phone-sheet.jpg', {
            type: 'image/jpeg',
        });
        await fireEvent.change(fileInput, { target: { files: [mockFile] } });

        await waitFor(() => {
            expect(
                container.querySelector('[data-omr-crop-pin="0"]'),
            ).not.toBeNull();
        });

        // Pressing a corner pin mounts the 2.5x magnifying loupe
        const topLeftPin = container.querySelector(
            '[data-omr-crop-pin="0"]',
        ) as HTMLElement;
        await fireEvent.mouseDown(topLeftPin);

        const loupe = container.querySelector('[data-omr-crop-loupe="true"]');
        expect(loupe).not.toBeNull();
        expect(loupe?.textContent).toContain('TL · 2.5×');

        await fireEvent.mouseUp(topLeftPin);
        expect(
            container.querySelector('[data-omr-crop-loupe="true"]'),
        ).toBeNull();

        // Clicking "Reset to Sheet" snaps pins to 2% margin
        const resetBtn = container.querySelector(
            '[data-omr-crop-reset="true"]',
        ) as HTMLElement;
        await fireEvent.click(resetBtn);
        expect(topLeftPin.style.left).toBe('2%');
        expect(topLeftPin.style.top).toBe('2%');

        // Clicking "Rotate 90°" increments rotation transform
        const rotateBtn = container.querySelector(
            '[data-omr-crop-rotate="true"]',
        ) as HTMLElement;
        await fireEvent.click(rotateBtn);
        const surface = container.querySelector('[data-omr-crop-rotation]');
        expect(surface?.getAttribute('data-omr-crop-rotation')).toBe('90');
    });

    it('opens the stored original for crop correction without discarding mapped guides', async () => {
        const fetchMock = vi.fn(async () => ({
            ok: true,
            blob: async () => new Blob(['photo'], { type: 'image/jpeg' }),
        }));
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('createImageBitmap', vi.fn(async () => ({
            width: 3000,
            height: 4000,
            close: vi.fn(),
        })));
        if (!URL.createObjectURL) URL.createObjectURL = vi.fn(() => 'blob:original-paper');
        else vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:original-paper');
        if (!URL.revokeObjectURL) URL.revokeObjectURL = vi.fn();

        const guide = {
            id: 'LOGIC_Q_001',
            name: 'Question 1',
            type: 'mcq',
            page_no: 1,
            startQ: 1,
            questionsCount: 1,
            optionsCount: 4,
            posX: 48,
            posY: 75,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 5.8,
            rowPitchMm: 6.8,
            isLocked: false,
        };
        const { container, getByRole } = render(OmrTemplateStudio, {
            draft: {
                id: 15,
                can_edit: true,
                lock_version: 42,
                registration_mode: 'document_bound',
                qr_mode: 'qr_disabled',
                source_kind: 'image',
                requested_page_count: 1,
                original_photo_url: '/setup/omr/templates/canonical/drafts/15/assets/155',
                stage: 'page_prepared',
                raster_url: '/assets/current-crop',
                persisted_preview: {
                    artifact: { canonical_url: '/assets/current-crop' },
                },
                authoring: {
                    source: { metadata: { original_asset_id: 155 } },
                    pages: [{ page_no: 1, asset_id: 156, confirmed: true }],
                    studio: {
                        current_stage: 3,
                        stage1Mode: 'upload',
                        baseType: 'image',
                        sections: [guide],
                    },
                },
            },
            marker_profiles: [],
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/15',
                source_rectify: '/setup/omr/studio/15/rectify',
            },
        });

        await fireEvent.click(getByRole('button', { name: 'Correct crop from original' }));
        await waitFor(() => {
            expect(container.querySelector('[data-omr-crop-pin="0"]')).not.toBeNull();
        });
        expect(fetchMock).toHaveBeenCalledWith(
            '/setup/omr/templates/canonical/drafts/15/assets/155',
            expect.objectContaining({ headers: { Accept: 'image/*' } }),
        );
        expect(container.textContent).toContain('Plotted Items (1)');
        await waitFor(() => {
            expect((getByRole('button', { name: 'Cancel' }) as HTMLButtonElement).disabled).toBe(false);
        });
        await fireEvent.click(getByRole('button', { name: 'Cancel' }));
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(container.textContent).toContain('Plotted Items (1)');
    });

    it('plots consecutive option points (A->B->C->D) into a solid item, resizes radius, copies/pastes with auto-increment, and supports undo/redo/delete', async () => {
        // 1. Pure domain helpers: build solid item -> resize concentrically -> re-spot single option -> clone with auto-increment -> sync inventory
        const q1 = buildSolidItemFromOptionPoints({
            id: 'LOGIC_P1_Q1',
            pageNo: 1,
            areaCode: 'LOGIC',
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 60 },
                { x: 36, y: 60 },
                { x: 42, y: 60 },
                { x: 48, y: 60 },
            ],
            bubbleDiameterMm: 4.5,
        });
        expect(q1.questionsCount).toBe(1);
        expect(q1.startQ).toBe(1);
        expect(q1.area_code).toBe('LOGIC');
        expect(q1.optionPointsMm).toHaveLength(4);
        expect(q1.optionPointsMm[0]).toEqual({ label: 'A', x_mm: 30, y_mm: 60 });
        expect(q1.optionPointsMm[3]).toEqual({ label: 'D', x_mm: 48, y_mm: 60 });

        // Resize diameter concentrically around option centers
        const resizedQ1 = resizeSolidItemBubbleDiameter(q1, 5.2);
        expect(resizedQ1.bubbleDiameterMm).toBeCloseTo(5.2, 1);
        expect(resizedQ1.optionPointsMm[0].x_mm).toBe(30);
        expect(resizedQ1.optionPointsMm[0].y_mm).toBe(60);

        // Re-spot single option B
        const respottedQ1 = updateSolidItemOptionPoint(resizedQ1, 1, {
            x: 36.5,
            y: 60.2,
        });
        expect(respottedQ1.optionPointsMm[1]).toEqual({
            label: 'B',
            x_mm: 36.5,
            y_mm: 60.2,
        });

        // Clone Q1 at row 2 anchor (30, 67) -> auto-increments to Q2
        const nextQ = nextItemNumberForArea([respottedQ1], 'LOGIC');
        expect(nextQ).toBe(2);
        const q2 = cloneSolidItemAtAnchor(
            respottedQ1,
            { x: 30, y: 67 },
            {
                id: 'LOGIC_P1_Q2',
                pageNo: 1,
                areaCode: 'LOGIC',
                itemNumber: nextQ,
            },
        );
        expect(q2.id).toBe('LOGIC_P1_Q2');
        expect(q2.startQ).toBe(2);
        expect(q2.optionPointsMm[0]).toEqual({ label: 'A', x_mm: 30, y_mm: 67 });
        expect(q2.optionPointsMm[3].x_mm).toBeCloseTo(48, 1);
        expect(q2.optionPointsMm[3].y_mm).toBeCloseTo(67, 1);

        // Sync inventory and verify readiness for multi-item plotted area
        const syncedInventory = syncDeclaredInventoryFromSections([
            respottedQ1,
            q2,
        ]);
        expect(syncedInventory).toHaveLength(1);
        expect(syncedInventory[0].area_code).toBe('LOGIC');
        expect(syncedInventory[0].question_count).toBe(2);

        const readiness = assessUploadedMappingReadiness({
            pageCount: 1,
            inventory: syncedInventory,
            identity: {
                method: 'write_in_manual',
                regions: [{ page_no: 1, x: 100, y: 100, w: 300, h: 80 }],
            },
            sections: [respottedQ1, q2],
        });
        expect(readiness.ready).toBe(true);
        expect(readiness.issues).toEqual([]);

        // 2. Interactive Canvas test: 4 consecutive clicks solidify Q1, then Copy & Paste stamps Q2, then Delete removes Q2
        const onGeometryChanged = vi.fn();
        const onUndo = vi.fn();
        const onRedo = vi.fn();
        const plottingConfig = {
            active: true,
            mode: 'consecutive',
            areaCode: 'VERBAL',
            itemNumber: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
            bubbleDiameterMm: 4.8,
            currentOptionIndex: 0,
            pointsMm: [],
            respotSectionIndex: null,
            respotOptionIndex: 0,
            stampSourceSection: null,
        };

        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/assets/sample.jpg',
            currentStage: 3,
            activePageNo: 1,
            scale: 1.0,
            panX: 0,
            panY: 0,
            sections: [],
            activeSectionIndex: 0,
            plottingConfig,
            onGeometryChanged,
            onUndo,
            onRedo,
        });

        const sheet = container.querySelector(
            '[data-omr-sheet="true"]',
        ) as HTMLElement;
        expect(
            container.querySelector('[data-omr-plotting-banner]'),
        ).not.toBeNull();

        // Move mouse to show live ghost circle follower
        await fireEvent.mouseMove(window, { clientX: 30 * 2.476, clientY: 50 * 2.476 });
        const ghost = container.querySelector(
            '[data-omr-ghost-follower="single"]',
        );
        expect(ghost).not.toBeNull();
        expect(ghost?.textContent).toContain('A');

        // Click 1 (Option A), Click 2 (Option B), Click 3 (Option C)
        // Click 1 (Option A) at (30mm, 50mm)
        await fireEvent.mouseDown(sheet, { clientX: 30 * 2.476, clientY: 50 * 2.476, button: 0 });
        expect(
            container.querySelectorAll('[data-omr-plotting-point]'),
        ).toHaveLength(1);

        // Click 2 (Option B) with Shift key held at offset Y (53mm) -> snaps to horizontal plane of first dot (50mm)!
        await fireEvent.mouseDown(sheet, { clientX: 36 * 2.476, clientY: 53 * 2.476, button: 0, shiftKey: true });
        // Click 3 (Option C) with Shift key held
        await fireEvent.mouseDown(sheet, { clientX: 42 * 2.476, clientY: 53 * 2.476, button: 0, shiftKey: true });
        expect(
            container.querySelectorAll('[data-omr-plotting-point]'),
        ).toHaveLength(3);

        // Click 4 (Option D) -> Solidifies into Q1 component!
        await fireEvent.mouseDown(sheet, { clientX: 48 * 2.476, clientY: 53 * 2.476, button: 0, shiftKey: true });
        const solidBadge = container.querySelector(
            '[data-omr-solid-item-badge]',
        );
        expect(solidBadge).not.toBeNull();
        expect(solidBadge?.textContent).toContain('VERBAL·Q1');
        expect(
            container.querySelectorAll('[data-omr-recognition-bubble]'),
        ).toHaveLength(4);

        // Verify floating copy-paste button was REMOVED from canvas to avoid clutter
        expect(container.querySelector('[data-omr-copy-paste-item="true"]')).toBeNull();

        // Copy & paste via keyboard shortcut: select Q1, press Ctrl+C then Ctrl+V to enter stamp mode
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'c', ctrlKey: true }));
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'v', ctrlKey: true }));

        // Stamp Q2 at (30mm, 58mm)
        await fireEvent.mouseDown(sheet, { clientX: 30 * 2.476, clientY: 58 * 2.476, button: 0 });
        const badges = Array.from(
            container.querySelectorAll('[data-omr-solid-item-badge]'),
        ).map((el) => el.textContent?.trim());
        expect(badges).toEqual(['VERBAL·Q1', 'VERBAL·Q2']);
        // Press Escape to exit stamp mode, then click Delete button on Q2
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        const deleteBtn = container.querySelector(
            '[data-omr-delete-section="true"]',
        ) as HTMLElement;
        expect(deleteBtn).not.toBeNull();
        await fireEvent.click(deleteBtn);
        expect(
            container.querySelectorAll('[data-omr-solid-item-badge]'),
        ).toHaveLength(1);

        // Verify Ctrl+Z / Ctrl+Y trigger Undo / Redo callbacks
        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'z', ctrlKey: true }),
        );
        expect(onUndo).toHaveBeenCalledTimes(1);
        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'y', ctrlKey: true }),
        );
        expect(onRedo).toHaveBeenCalledTimes(1);
    });

    it('multiplies items downward with exact millimeter pitch and detects adjacent step', () => {
        const baseItem = buildSolidItemFromOptionPoints({
            id: 'VERBAL_P1_Q1',
            pageNo: 1,
            areaCode: 'VERBAL',
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 50 },
                { x: 36, y: 50 },
                { x: 42, y: 50 },
                { x: 48, y: 50 },
            ],
            bubbleDiameterMm: 4.5,
        });

        // Multiply 4 items downward at exact 6.85mm pitch
        const multiplied = multiplyItemsDownward(
            baseItem,
            { count: 4, distancePerItemMm: 6.85, areaCode: 'VERBAL', pageNo: 1 },
            [baseItem],
        );

        expect(multiplied).toHaveLength(4);
        expect(multiplied.map((m) => m.startQ)).toEqual([2, 3, 4, 5]);
        expect(multiplied.map((m) => m.name)).toEqual([
            'VERBAL · Q2',
            'VERBAL · Q3',
            'VERBAL · Q4',
            'VERBAL · Q5',
        ]);

        // Verify exact vertical pitch down the page
        const expectedY2 = Number((50 + 6.85).toFixed(2));
        const expectedY3 = Number((50 + 2 * 6.85).toFixed(2));
        expect(multiplied[0].optionPointsMm[0].y_mm).toBeCloseTo(expectedY2, 2);
        expect(multiplied[1].optionPointsMm[0].y_mm).toBeCloseTo(expectedY3, 2);

        // Detect adjacent vertical step from [baseItem, ...multiplied]
        const detected = detectAdjacentVerticalStepMm(
            [baseItem, ...multiplied],
            'VERBAL',
            1,
        );
        expect(detected).toBeCloseTo(6.85, 1);
    });

    it('supports bi-directional multi-selection and bulk aptitude area reassignment', async () => {
        let sections = [
            buildSolidItemFromOptionPoints({
                id: 'LOGIC_P1_Q1',
                pageNo: 1,
                areaCode: 'LOGIC',
                itemNumber: 1,
                optionLabels: ['A', 'B', 'C', 'D'],
                pointsMm: [{ x: 30, y: 50 }, { x: 36, y: 50 }, { x: 42, y: 50 }, { x: 48, y: 50 }],
                bubbleDiameterMm: 4.5,
            }),
            buildSolidItemFromOptionPoints({
                id: 'LOGIC_P1_Q2',
                pageNo: 1,
                areaCode: 'LOGIC',
                itemNumber: 2,
                optionLabels: ['A', 'B', 'C', 'D'],
                pointsMm: [{ x: 30, y: 56.8 }, { x: 36, y: 56.8 }, { x: 42, y: 56.8 }, { x: 48, y: 56.8 }],
                bubbleDiameterMm: 4.5,
            }),
            buildSolidItemFromOptionPoints({
                id: 'LOGIC_P1_Q3',
                pageNo: 1,
                areaCode: 'LOGIC',
                itemNumber: 3,
                optionLabels: ['A', 'B', 'C', 'D'],
                pointsMm: [{ x: 30, y: 63.6 }, { x: 36, y: 63.6 }, { x: 42, y: 63.6 }, { x: 48, y: 63.6 }],
                bubbleDiameterMm: 4.5,
            }),
            buildSolidItemFromOptionPoints({
                id: 'LOGIC_P1_Q4',
                pageNo: 1,
                areaCode: 'LOGIC',
                itemNumber: 4,
                optionLabels: ['A', 'B', 'C', 'D'],
                pointsMm: [{ x: 30, y: 70.4 }, { x: 36, y: 70.4 }, { x: 42, y: 70.4 }, { x: 48, y: 70.4 }],
                bubbleDiameterMm: 4.5,
            }),
        ];
        let activeSectionIndex = 0;
        let selectedSectionIndices = [0];
        let plottingConfig = {
            active: false,
            mode: 'consecutive',
            areaCode: 'LOGIC',
            itemNumber: 5,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
            bubbleDiameterMm: 4.5,
            currentOptionIndex: 0,
            pointsMm: [],
        };

        const { container } = render(StudioBubblePanel, {
            props: {
                sections,
                activeSectionIndex,
                selectedSectionIndices,
                plottingConfig,
                isUploadedSource: true,
                pageCount: 1,
                aptitudeAreas: [
                    { code: 'LOGIC', name: 'Logic' },
                    { code: 'VERBAL', name: 'Verbal' },
                ],
            },
        });

        // Click Q1 (index 0)
        const chip0 = container.querySelector('[data-omr-solid-item-chip="LOGIC_P1_Q1"]') as HTMLElement;
        expect(chip0).not.toBeNull();
        await fireEvent.click(chip0);

        // Shift-click Q3 (index 2) -> selects range Q1, Q2, Q3!
        const chip2 = container.querySelector('[data-omr-solid-item-chip="LOGIC_P1_Q3"]') as HTMLElement;
        expect(chip2).not.toBeNull();
        await fireEvent.click(chip2, { shiftKey: true });

        // Verify bulk toolbar is displayed
        const bulkToolbar = container.querySelector('[data-omr-bulk-actions-toolbar="true"]');
        expect(bulkToolbar).not.toBeNull();
        expect(bulkToolbar?.textContent).toContain('3 items selected');

        // Reassign to VERBAL
        const bulkSelect = container.querySelector('[data-omr-bulk-area-select="true"]') as HTMLSelectElement;
        if (bulkSelect) {
            await fireEvent.change(bulkSelect, { target: { value: 'VERBAL' } });
        }
        const reassignBtn = container.querySelector('[data-omr-bulk-reassign-btn="true"]') as HTMLElement;
        expect(reassignBtn).not.toBeNull();
        await fireEvent.click(reassignBtn);

        // Verify Q1, Q2, Q3 chips now display VERBAL
        const updatedChips = Array.from(container.querySelectorAll('[data-omr-solid-item-chip]')).map(c => c.textContent?.trim());
        expect(updatedChips[0]).toContain('VERBAL · Q1');
        expect(updatedChips[1]).toContain('VERBAL · Q2');
        expect(updatedChips[2]).toContain('VERBAL · Q3');
        expect(updatedChips[3]).toContain('LOGIC · Q4');
    });

    it('supports applicant number roll grid in vertical columns and horizontal rows', () => {
        const verticalRoll = {
            id: 'Applicant_Roll_V',
            name: 'Applicant Number · Page 1',
            type: 'roll',
            page_no: 1,
            identity_grid: true,
            questionsCount: 6, // 6 digits
            rollDirection: 'vertical',
            posX: 50,
            posY: 30,
            w_mm: 35,
            h_mm: 50,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 5.5,
            rowPitchMm: 4.8,
            isLocked: false,
        };

        const horizontalRoll = {
            id: 'Applicant_Roll_H',
            name: 'Applicant Number · Page 1',
            type: 'roll',
            page_no: 1,
            identity_grid: true,
            questionsCount: 6, // 6 digits
            rollDirection: 'horizontal',
            posX: 50,
            posY: 30,
            w_mm: 50,
            h_mm: 35,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 4.8,
            rowPitchMm: 5.5,
            isLocked: false,
        };

        const { container: vContainer } = render(StudioCanvas, {
            props: {
                currentStage: 3,
                sections: [verticalRoll],
                activeSectionIndex: 0,
                activePageNo: 1,
                canvasLocked: false,
            },
        });

        // 6 columns * 10 rows = 60 bubbles
        const vBubbles = vContainer.querySelectorAll('[data-omr-recognition-bubble]');
        expect(vBubbles).toHaveLength(60);

        const { container: hContainer } = render(StudioCanvas, {
            props: {
                currentStage: 3,
                sections: [horizontalRoll],
                activeSectionIndex: 0,
                activePageNo: 1,
                canvasLocked: false,
            },
        });

        // 6 rows * 10 columns = 60 bubbles
        const hBubbles = hContainer.querySelectorAll('[data-omr-recognition-bubble]');
        expect(hBubbles).toHaveLength(60);
    });
    it('tilts only the vertical shaded Applicant Number grid in bounded fine and coarse steps', async () => {
        const verticalGrid = {
            id: 'Applicant_Number_Page_1',
            type: 'roll',
            page_no: 1,
            identity_grid: true,
            questionsCount: 6,
            rollDirection: 'vertical',
            posX: 50,
            posY: 30,
            w_mm: 35,
            h_mm: 50,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 5.5,
            rowPitchMm: 4.8,
            rotationDeg: 0,
            isLocked: false,
        };
        const { container, getByRole } = render(StudioBubblePanel, {
            props: {
                sections: [verticalGrid],
                activeSectionIndex: 0,
                selectedSectionIndices: [],
                isUploadedSource: true,
                pageCount: 1,
                identity: { method: 'shaded_grid', digit_count: 6 },
            },
        });
        const fineNudge = getByRole('button', {
            name: 'Tilt Applicant Number grid on Page 1 by +0.1 degrees',
        });
        const coarseNudge = getByRole('button', {
            name: 'Tilt Applicant Number grid on Page 1 by +0.5 degrees',
        });

        await fireEvent.click(fineNudge);
        expect(container.querySelector('[data-omr-applicant-tilt="1"]')?.textContent).toContain('+0.1°');
        await fireEvent.click(coarseNudge);
        expect(container.querySelector('[data-omr-applicant-tilt="1"]')?.textContent).toContain('+0.6°');

        for (let step = 0; step < 8; step += 1) {
            await fireEvent.click(coarseNudge);
        }
        expect(container.querySelector('[data-omr-applicant-tilt="1"]')?.textContent).toContain('+4.6°');
        expect((coarseNudge as HTMLButtonElement).disabled).toBe(true);

        for (let step = 0; step < 4; step += 1) {
            await fireEvent.click(fineNudge);
        }
        expect(container.querySelector('[data-omr-applicant-tilt="1"]')?.textContent).toContain('+5.0°');
        expect((fineNudge as HTMLButtonElement).disabled).toBe(true);
    });

    it('sends identity method changes to the template owner for persistence', async () => {
        const onIdentityChanged = vi.fn();
        const { getByRole } = render(StudioBubblePanel, {
            props: {
                sections: [],
                activeSectionIndex: -1,
                selectedSectionIndices: [],
                isUploadedSource: true,
                pageCount: 1,
                identity: { method: null },
                onIdentityChanged,
            },
        });

        await fireEvent.click(getByRole('button', { name: /Shaded Grid/ }));

        expect(onIdentityChanged).toHaveBeenCalledWith({
            method: 'shaded_grid',
            digit_count: null,
        });
    });
    it('persists the selected shaded-grid identity from the template owner', async () => {
        const requests: Array<{ url: string; options?: RequestInit }> = [];
        const fetchMock = vi.fn(
            async (input: string | URL, options?: RequestInit) => {
                requests.push({ url: String(input), options });
                return {
                    ok: true,
                    json: async () => ({ lock_version: 2, stage: 'draft' }),
                };
            },
        );
        vi.stubGlobal('fetch', fetchMock);

        const { getByRole, getByLabelText } = render(OmrTemplateStudio, {
            draft: {
                id: 88,
                can_edit: true,
                lock_version: 1,
                registration_mode: 'document_bound',
                qr_mode: 'qr_disabled',
                source_kind: 'image',
                requested_page_count: 1,
                stage: 'draft',
                authoring: {
                    studio: {
                        current_stage: 3,
                        stage1Mode: 'upload',
                        baseType: 'image',
                        sections: [
                            {
                                id: 'Applicant_Number_Page_1',
                                name: 'Applicant Number · Page 1',
                                type: 'roll',
                                page_no: 1,
                                identity_grid: true,
                                questionsCount: 6,
                                rollDirection: 'vertical',
                                posX: 50,
                                posY: 30,
                                w_mm: 35,
                                h_mm: 50,
                                bubbleDiameterMm: 4.5,
                                bubblePitchMm: 5.5,
                                rowPitchMm: 4.8,
                                isLocked: false,
                            },
                        ],
                    },
                },
            },
            marker_profiles: [],
            aptitude_areas: [],
            urls: { studio: '/setup/omr/studio/88' },
        });

        await fireEvent.click(getByRole('button', { name: /Shaded Grid/ }));
        await fireEvent.input(getByLabelText('Applicant Number grid digit count'), {
            target: { value: '6' },
        });
        await fireEvent.click(getByRole('button', { name: 'Save' }));

        await waitFor(() => {
            const saveRequest = requests.find(
                ({ url, options }) =>
                    url === '/setup/omr/studio/88' &&
                    options?.method === 'PUT',
            );
            expect(saveRequest).toBeDefined();
            const payload = JSON.parse(
                saveRequest?.options?.body as string,
            );
            expect(payload.studio.identity).toEqual({
                method: 'shaded_grid',
                digit_count: 6,
            });
            expect(payload.mapping.identity).toEqual(payload.studio.identity);
        });
    });
    it('interpolates 10 digit points from a 2-click span and builds applicant roll grid', () => {
        const p0 = { x: 50, y: 30 };
        const p9 = { x: 50, y: 73.2 };

        const points = interpolateTenDigitPointsFromSpan(p0, p9);
        expect(points).toHaveLength(10);
        expect(points[0].label).toBe('0');
        expect(points[9].label).toBe('9');
        expect(points[0].y).toBe(30);
        expect(points[9].y).toBe(73.2);
        expect(points[1].y).toBeCloseTo(34.8, 1);

        const roll = buildApplicantDigitRollFromPoints({
            pageNo: 1,
            digitCount: 6,
            pointsMm: points,
            bubbleDiameterMm: 4.5,
            stepPitchMm: 5.5,
        });

        expect(roll.type).toBe('roll');
        expect(roll.identity_grid).toBe(true);
        expect(roll.questionsCount).toBe(6);
        expect(roll.rollDirection).toBe('vertical');
        expect(roll.rowPitchMm).toBeCloseTo(4.8, 1);
        expect(roll.bubblePitchMm).toBe(5.5);
    });

    it('multiplies question items horizontally when direction is horizontal', () => {
        const baseItem = buildSolidItemFromOptionPoints({
            id: 'MATH_P1_Q1',
            pageNo: 1,
            areaCode: 'MATH',
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 50 },
                { x: 36, y: 50 },
                { x: 42, y: 50 },
                { x: 48, y: 50 },
            ],
            bubbleDiameterMm: 4.5,
        });

        // Multiply 3 items horizontally at 25mm column spacing
        const multiplied = multiplyItemsDownward(
            baseItem,
            { count: 3, distancePerItemMm: 25.0, areaCode: 'MATH', pageNo: 1, direction: 'horizontal' },
            [baseItem],
        );

        expect(multiplied).toHaveLength(3);
        expect(multiplied[0].startQ).toBe(2);
        expect(multiplied[1].startQ).toBe(3);
        expect(multiplied[2].startQ).toBe(4);

        // Verify horizontal progression: Y stays 50, X shifts by 25mm
        expect(multiplied[0].optionPointsMm[0].x_mm).toBeCloseTo(55.0, 1);
        expect(multiplied[0].optionPointsMm[0].y_mm).toBeCloseTo(50.0, 1);
        expect(multiplied[1].optionPointsMm[0].x_mm).toBeCloseTo(80.0, 1);
        expect(multiplied[1].optionPointsMm[0].y_mm).toBeCloseTo(50.0, 1);
    });

    it('plots applicant roll grid on canvas via 2-click span shortcut', async () => {
        let sections: Array<Record<string, unknown>> = [];
        let activeSectionIndex = 0;
        let plottingConfig = {
            active: true,
            targetType: 'applicant_digit',
            digitSpanMode: true,
            optionsCount: 10,
            optionLabels: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            currentOptionIndex: 0,
            pointsMm: [],
            digitCount: 6,
            stepPitchMm: 5.5,
            bubbleDiameterMm: 4.5,
        };

        const { container } = render(StudioCanvas, {
            props: {
                currentStage: 3,
                sections,
                activeSectionIndex,
                plottingConfig,
                activePageNo: 1,
                canvasLocked: false,
            },
        });

        const sheet = container.querySelector('[data-omr-sheet="true"]') as HTMLElement;
        expect(sheet).not.toBeNull();

        // Click 1 (Bubble 0) at (50mm, 30mm)
        await fireEvent.mouseDown(sheet, { clientX: 50 * 2.476, clientY: 30 * 2.476, button: 0 });

        // Click 2 (Bubble 9) at (50mm, 73.2mm) with Shift held
        await fireEvent.mouseDown(sheet, { clientX: 50 * 2.476, clientY: 73.2 * 2.476, button: 0, shiftKey: true });

        // Verify roll section was created on canvas
        const rollSection = container.querySelector('[data-omr-section-id*="Applicant_Number"]');
        expect(rollSection).not.toBeNull();

        // Verify 6 columns * 10 rows = 60 recognition bubbles
        const bubbles = container.querySelectorAll('[data-omr-recognition-bubble]');
        expect(bubbles).toHaveLength(60);
    });

    it('rotates a single solid item and groups of items around their centers', () => {
        const itemA = buildSolidItemFromOptionPoints({
            id: 'TILT_P1_Q1',
            pageNo: 1,
            areaCode: 'TILT',
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 50 },
                { x: 36, y: 50 },
                { x: 42, y: 50 },
                { x: 48, y: 50 },
            ],
            bubbleDiameterMm: 4.5,
        });

        // Rotate single item by 90 degrees around first point (30, 50)
        const rotated = rotateSolidItem(itemA, 90, { x: 30, y: 50 });
        expect(rotated.rotationDeg).toBe(90);
        // X stays 30, Y rotates downwards: 50, 56, 62, 68
        expect(rotated.optionPointsMm[0].x_mm).toBeCloseTo(30, 1);
        expect(rotated.optionPointsMm[0].y_mm).toBeCloseTo(50, 1);
        expect(rotated.optionPointsMm[1].x_mm).toBeCloseTo(30, 1);
        expect(rotated.optionPointsMm[1].y_mm).toBeCloseTo(56, 1);

        // Group rotation: rotate 2 items together
        const itemB = buildSolidItemFromOptionPoints({
            id: 'TILT_P1_Q2',
            pageNo: 1,
            areaCode: 'TILT',
            itemNumber: 2,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 60 },
                { x: 36, y: 60 },
                { x: 42, y: 60 },
                { x: 48, y: 60 },
            ],
            bubbleDiameterMm: 4.5,
        });

        const groupRotated = rotateGroupOfItems([itemA, itemB], [0, 1], 1.5);
        expect(groupRotated).toHaveLength(2);
        expect(groupRotated[0].rotationDeg).toBe(1.5);
        expect(groupRotated[1].rotationDeg).toBe(1.5);
    });

    it('propagates question items with slantAngleDeg down a tilted page', () => {
        const baseItem = buildSolidItemFromOptionPoints({
            id: 'SLANT_P1_Q1',
            pageNo: 1,
            areaCode: 'SLANT',
            itemNumber: 1,
            optionLabels: ['A', 'B', 'C', 'D'],
            pointsMm: [
                { x: 30, y: 50 },
                { x: 36, y: 50 },
                { x: 42, y: 50 },
                { x: 48, y: 50 },
            ],
            bubbleDiameterMm: 4.5,
        });

        // Multiply 2 items downward with a 5.0 degree slant
        const slanted = multiplyItemsDownward(
            baseItem,
            { count: 2, distancePerItemMm: 10.0, areaCode: 'SLANT', pageNo: 1, slantAngleDeg: 5.0 },
            [baseItem],
        );

        expect(slanted).toHaveLength(2);
        // sin(5 deg) * 10 = ~0.87mm shift in X per row!
        // cos(5 deg) * 10 = ~9.96mm shift in Y per row!
        expect(slanted[0].optionPointsMm[0].x_mm).toBeGreaterThan(30.5);
        expect(slanted[0].optionPointsMm[0].y_mm).toBeCloseTo(59.96, 1);
        expect(slanted[1].optionPointsMm[0].x_mm).toBeGreaterThan(31.5);
        expect(slanted[1].optionPointsMm[0].y_mm).toBeCloseTo(69.92, 1);
    });
});
