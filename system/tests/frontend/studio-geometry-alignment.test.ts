// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';
import StudioCanvas from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioCanvas.svelte';
import {
    type PlottedItemSection,
    buildApplicantDigitRollFromPoints,
    buildSmartSnapSearchZoneOverlay,
    emitNativeMapping,
    mmToPx,
    PX_PER_MM,
    recognitionOriginOffsetMm,
    remapPageDeclarations,
    resetSectionOptionPointsToUniformGrid,
    rescaleSectionOptionPointsPitch,
    setSectionAbsoluteTiltDeg,
    snapSectionsToPrintedCircles,
    validateStudioLayout,
} from '../../resources/js/lib/omrStudioGeometry';

const CANVAS_PX_PER_MM = 2.476;
const PIXEL_ROUNDING_TOLERANCE_MM = 0.3;

function searchZoneSection(id: string, optionPointsMm: unknown[]): PlottedItemSection {
    const rows = optionPointsMm.length > 0 && Array.isArray(optionPointsMm[0])
        ? optionPointsMm as Array<Array<{ label: string; x_mm: number; y_mm: number }>>
        : [optionPointsMm as Array<{ label: string; x_mm: number; y_mm: number }>];
    return {
        id,
        name: id,
        type: 'mcq',
        page_no: 1,
        area_code: 'GENERAL',
        aptitudeAreaCode: 'GENERAL',
        startQ: 1,
        questionsCount: rows.length,
        optionsCount: rows[0]?.length ?? 2,
        customValuesStr: '',
        posX: 0,
        posY: 0,
        w_mm: 20,
        h_mm: 20,
        bubbleDiameterMm: 4.5,
        bubblePitchMm: 8,
        rowPitchMm: 10,
        cols: 1,
        isLocked: false,
        optionPointsMm: optionPointsMm as unknown as PlottedItemSection['optionPointsMm'],
        optionLabels: rows[0]?.map((point) => point.label),
    } as unknown as PlottedItemSection;
}

function rollSection(bubblePitchMm: number, rowPitchMm: number) {
    return {
        id: 'Examinee_ID',
        name: 'Examinee Number',
        type: 'roll' as const,
        posX: 110,
        posY: 28,
        w_mm: 58,
        h_mm: 52,
        questionsCount: 7,
        cols: 7,
        optionsCount: 10,
        startQ: 1,
        bubblePitchMm,
        bubbleDiameterMm: 4.5,
        rowPitchMm,
        isLocked: false,
    };
}

function nativeRollBlock(section: ReturnType<typeof rollSection>) {
    return emitNativeMapping(210, 297, [
        {
            id: section.id,
            type: 'roll',
            x_mm: section.posX,
            y_mm: section.posY,
            w_mm: section.w_mm,
            h_mm: section.h_mm,
            digits: section.questionsCount,
            bubble_pitch_mm: section.bubblePitchMm,
            row_pitch_mm: section.rowPitchMm,
        },
    ]).blocks[0];
}

function renderRoll(section: ReturnType<typeof rollSection>) {
    const rendered = render(StudioCanvas, {
        currentStage: 3,
        previewSurface: 'canonical',
        canonicalPreview: null,
        mappingPreviewStale: true,
        sections: [section],
        components: [],
    });
    const sectionElement = rendered.container.querySelector(
        '[data-omr-section-id="Examinee_ID"]',
    );
    expect(sectionElement).not.toBeNull();
    const bubbles = Array.from(
        sectionElement?.querySelectorAll('[data-omr-recognition-bubble]') ?? [],
    );
    expect(bubbles).toHaveLength(70);
    return { ...rendered, bubbles };
}

function assertBubbleAlignment(section: ReturnType<typeof rollSection>) {
    const mappingBlock = nativeRollBlock(section);
    const origin = recognitionOriginOffsetMm({
        id: section.id,
        type: 'roll',
        x_mm: section.posX,
        y_mm: section.posY,
        w_mm: section.w_mm,
        h_mm: section.h_mm,
    });
    const { bubbles } = renderRoll(section);

    bubbles.forEach((bubble, index) => {
        const row = Math.floor(index / section.questionsCount);
        const column = index % section.questionsCount;
        const leftPx = Number.parseFloat((bubble as HTMLElement).style.left);
        const topPx = Number.parseFloat((bubble as HTMLElement).style.top);
        const widthPx = Number.parseFloat((bubble as HTMLElement).style.width);
        const heightPx = Number.parseFloat(
            (bubble as HTMLElement).style.height,
        );
        const actual = {
            x_mm: section.posX + origin.x + leftPx / CANVAS_PX_PER_MM,
            y_mm: section.posY + origin.y + topPx / CANVAS_PX_PER_MM,
            w_mm: widthPx / CANVAS_PX_PER_MM,
            h_mm: heightPx / CANVAS_PX_PER_MM,
        };
        const expected = {
            x_mm:
                (mappingBlock.origin_x + column * mappingBlock.row_pitch_px) /
                PX_PER_MM,
            y_mm:
                (mappingBlock.origin_y + row * mappingBlock.option_pitch_px) /
                PX_PER_MM,
            w_mm: mappingBlock.bubble_width_px / PX_PER_MM,
            h_mm: mappingBlock.bubble_height_px / PX_PER_MM,
        };

        expect(Math.abs(actual.x_mm - expected.x_mm)).toBeLessThan(
            PIXEL_ROUNDING_TOLERANCE_MM,
        );
        expect(Math.abs(actual.y_mm - expected.y_mm)).toBeLessThan(
            PIXEL_ROUNDING_TOLERANCE_MM,
        );
        expect(Math.abs(actual.w_mm - expected.w_mm)).toBeLessThan(
            PIXEL_ROUNDING_TOLERANCE_MM,
        );
        expect(Math.abs(actual.h_mm - expected.h_mm)).toBeLessThan(
            PIXEL_ROUNDING_TOLERANCE_MM,
        );
    });
}

describe('Studio recognition geometry parity', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });
    it('keeps plotted Applicant Number span endpoints at their native bubbles', () => {
        const firstPoint = { x: 40.1, y: 40.1 };
        const lastPoint = { x: 40.1, y: 104.9 };
        const section = buildApplicantDigitRollFromPoints({
            pageNo: 1,
            digitCount: 6,
            pointsMm: [
                { label: '0', ...firstPoint },
                { label: '9', ...lastPoint },
            ],
            rollDirection: 'vertical',
        });
        const block = emitNativeMapping(210, 297, [
            {
                id: String(section.id),
                type: 'roll',
                page_no: 1,
                x_mm: Number(section.posX),
                y_mm: Number(section.posY),
                w_mm: Number(section.w_mm),
                h_mm: Number(section.h_mm),
                digits: Number(section.questionsCount),
                bubble_diameter_mm: Number(section.bubbleDiameterMm),
                bubble_pitch_mm: Number(section.bubblePitchMm),
                row_pitch_mm: Number(section.rowPitchMm),
            },
        ]).blocks[0];
        const bubbleRadiusMm = Number(section.bubbleDiameterMm) / 2;

        expect(block.origin_x).toBe(
            mmToPx(firstPoint.x - bubbleRadiusMm),
        );
        expect(block.origin_y).toBe(
            mmToPx(firstPoint.y - bubbleRadiusMm),
        );
        expect(
            Math.abs(
                block.origin_y +
                    9 * block.option_pitch_px -
                    mmToPx(lastPoint.y - bubbleRadiusMm),
            ),
        ).toBeLessThanOrEqual(1);
    });

    it('remaps page-bound inventory and identity geometry without assigning unmapped pages', () => {
        const result = remapPageDeclarations(
            [
                { id: 'page-one', page_no: 1, area_code: 'A', geometry_id: 'g1' },
                { id: 'page-two', page_no: 2, area_code: 'B', geometry_id: 'g2' },
                { id: 'removed-page', page_no: 3, area_code: 'C', geometry_id: 'g3' },
                { id: 'invalid-page', page_no: null, area_code: 'D' },
            ],
            {
                method: 'shaded_grid',
                digit_count: 6,
                regions: [
                    { page_no: 1, x: 10, y: 20, w: 30, h: 40 },
                    { page_no: 2, x: 110, y: 120, w: 130, h: 140 },
                    { page_no: 3, x: 210, y: 220, w: 230, h: 240 },
                    { page_no: null, x: 310, y: 320, w: 330, h: 340 },
                ],
            },
            new Map([
                [1, 2],
                [2, 1],
            ]),
        );

        expect(result.inventory).toEqual([
            { id: 'page-one', page_no: 2, area_code: 'A', geometry_id: 'g1' },
            { id: 'page-two', page_no: 1, area_code: 'B', geometry_id: 'g2' },
        ]);
        expect(result.identity).toEqual({
            method: 'shaded_grid',
            digit_count: 6,
            regions: [
                { page_no: 2, x: 10, y: 20, w: 30, h: 40 },
                { page_no: 1, x: 110, y: 120, w: 130, h: 140 },
            ],
        });
        expect(
            remapPageDeclarations(
                [],
                {
                    method: 'write_in_manual',
                    regions: [
                        { page_no: 2, x: 12, y: 22, w: 32, h: 42 },
                    ],
                },
                new Map([[2, 1]]),
            ).identity,
        ).toEqual({
            method: 'write_in_manual',
            regions: [{ page_no: 1, x: 12, y: 22, w: 32, h: 42 }],
        });
    });

    it('keeps every examinee-number bubble aligned when raw row pitch is below the native minimum', () => {
        assertBubbleAlignment(rollSection(4.8, 4.8));
    });

    it('preserves safe roll pitches and the first native origin after px/mm conversion', () => {
        const section = rollSection(6.2, 6.8);
        const mappingBlock = nativeRollBlock(section);

        expect(mappingBlock.row_pitch_px).toBe(mmToPx(section.bubblePitchMm));
        expect(mappingBlock.option_pitch_px).toBe(mmToPx(section.rowPitchMm));
        assertBubbleAlignment(section);
    });
    it('serializes tilted questions and vertical Applicant Number grids at the canvas bubble positions', () => {
        const optionPointsMm = [
            { label: 'A', x_mm: 40, y_mm: 60 },
            { label: 'B', x_mm: 46, y_mm: 60.1 },
            { label: 'C', x_mm: 52, y_mm: 60.3 },
            { label: 'D', x_mm: 58, y_mm: 60.5 },
        ];
        const question = emitNativeMapping(210, 297, [
            {
                id: 'TILTED_Q1',
                type: 'mcq',
                x_mm: 30,
                y_mm: 50,
                w_mm: 30,
                h_mm: 5,
                questions: 1,
                options: 4,
                bubble_diameter_mm: 4.5,
                option_points_mm: optionPointsMm,
                rotation_deg: 1.2,
            },
        ]).blocks[0];
        const bubbleHalfPx = Math.floor(question.bubble_width_px / 2);
        expect(question.bubble_positions).toEqual([
            optionPointsMm.map((point) => [
                mmToPx(point.x_mm) - bubbleHalfPx,
                mmToPx(point.y_mm) - bubbleHalfPx,
            ]),
        ]);
        expect(question.width).toBeGreaterThan(0);
        expect(question.height).toBeGreaterThan(0);
        const questionSection = {
            id: 'TILTED_Q1',
            type: 'mcq' as const,
            page_no: 1,
            posX: 30,
            posY: 50,
            w_mm: 30,
            h_mm: 5,
            questionsCount: 1,
            optionsCount: 4,
            bubbleDiameterMm: 4.5,
            bubblePitchMm: 6,
            rowPitchMm: 6.8,
            optionPointsMm,
            isLocked: false,
        };
        const canvas = render(StudioCanvas, {
            currentStage: 3,
            previewSurface: 'canonical',
            canonicalPreview: null,
            mappingPreviewStale: true,
            sections: [questionSection],
            components: [],
        });
        const questionWrapper = canvas.container.querySelector(
            '[data-omr-section-id="TILTED_Q1"]',
        ) as HTMLElement;
        const wrapperX = Number.parseFloat(questionWrapper.style.left) / CANVAS_PX_PER_MM;
        const wrapperY = Number.parseFloat(questionWrapper.style.top) / CANVAS_PX_PER_MM;
        const renderedOptionBubbles = Array.from(
            questionWrapper.querySelectorAll('[data-omr-recognition-bubble]'),
        ) as HTMLElement[];

        expect(
            renderedOptionBubbles.map((bubble) => [
                mmToPx(
                    wrapperX +
                        Number.parseFloat(bubble.style.left) /
                            CANVAS_PX_PER_MM,
                ),
                mmToPx(
                    wrapperY +
                        Number.parseFloat(bubble.style.top) /
                            CANVAS_PX_PER_MM,
                ),
            ]),
        ).toEqual(question.bubble_positions?.[0]);

        const identityBlock = emitNativeMapping(210, 297, [
            {
                id: 'Applicant_Number_Page_1',
                type: 'roll',
                identity_grid: true,
                roll_direction: 'vertical',
                rotation_deg: 1.2,
                x_mm: 110,
                y_mm: 28,
                w_mm: 38,
                h_mm: 48,
                digits: 6,
                bubble_diameter_mm: 4.5,
                bubble_pitch_mm: 5.5,
                row_pitch_mm: 4.8,
            },
        ]).blocks[0];
        expect(identityBlock.bubble_positions).toHaveLength(6);
        expect(identityBlock.bubble_positions?.every((row) => row.length === 10)).toBe(true);
        expect(identityBlock.bubble_positions?.[0]?.[0]).toEqual([
            identityBlock.origin_x,
            identityBlock.origin_y,
        ]);
        expect(identityBlock.bubble_positions?.[0]?.[1]?.[0]).not.toBe(
            identityBlock.origin_x,
        );

        const unchanged = emitNativeMapping(210, 297, [
            {
                id: 'Applicant_Number_Page_1',
                type: 'roll',
                identity_grid: true,
                roll_direction: 'vertical',
                x_mm: 110,
                y_mm: 28,
                w_mm: 38,
                h_mm: 48,
                digits: 6,
                bubble_diameter_mm: 4.5,
                bubble_pitch_mm: 5.5,
                row_pitch_mm: 4.8,
            },
        ]).blocks[0];
        expect(unchanged.bubble_positions).toBeUndefined();
    });

    it('draws the tilted Applicant Number grid at its serialized top-left pixels', () => {
        const section = {
            ...rollSection(5.5, 4.8),
            identity_grid: true,
            rotationDeg: 1.2,
        };
        const mappingBlock = emitNativeMapping(210, 297, [
            {
                id: section.id,
                type: 'roll',
                identity_grid: true,
                roll_direction: 'vertical',
                rotation_deg: section.rotationDeg,
                x_mm: section.posX,
                y_mm: section.posY,
                w_mm: section.w_mm,
                h_mm: section.h_mm,
                digits: section.questionsCount,
                bubble_diameter_mm: section.bubbleDiameterMm,
                bubble_pitch_mm: section.bubblePitchMm,
                row_pitch_mm: section.rowPitchMm,
            },
        ]).blocks[0];
        const { container } = renderRoll(section);
        const wrapper = container.querySelector(
            '[data-omr-section-id="Examinee_ID"]',
        ) as HTMLElement;
        const wrapperX = Number.parseFloat(wrapper.style.left) / CANVAS_PX_PER_MM;
        const wrapperY = Number.parseFloat(wrapper.style.top) / CANVAS_PX_PER_MM;
        const firstOptionOfSecondDigit = container.querySelectorAll(
            '[data-omr-recognition-bubble]',
        )[10] as HTMLElement;
        const actualTopLeft = [
            mmToPx(
                wrapperX +
                    Number.parseFloat(firstOptionOfSecondDigit.style.left) /
                        CANVAS_PX_PER_MM,
            ),
            mmToPx(
                wrapperY +
                    Number.parseFloat(firstOptionOfSecondDigit.style.top) /
                        CANVAS_PX_PER_MM,
            ),
        ];

        expect(actualTopLeft).toEqual(mappingBlock.bubble_positions?.[1]?.[0]);
    });
    it('blocks tilted geometry outside the page and refuses missing rotated question points', () => {
        const outOfBounds = validateStudioLayout(
            { width_mm: 210, height_mm: 297, margin_mm: 10 },
            { top_mm: 12, right_mm: 12, bottom_mm: 14, left_mm: 12 },
            [
                {
                    id: 'Applicant_Number_Page_1',
                    type: 'roll',
                    identity_grid: true,
                    roll_direction: 'vertical',
                    rotation_deg: 5,
                    x_mm: 207,
                    y_mm: 28,
                    w_mm: 38,
                    h_mm: 48,
                    digits: 6,
                    bubble_diameter_mm: 4.5,
                    bubble_pitch_mm: 5.5,
                    row_pitch_mm: 4.8,
                },
            ],
        );
        expect(outOfBounds.valid).toBe(false);
        expect(
            outOfBounds.warnings.some(
                (warning) => warning.code === 'bubble_out_of_bounds',
            ),
        ).toBe(true);

        const missingQuestionPoints = validateStudioLayout(
            { width_mm: 210, height_mm: 297, margin_mm: 10 },
            { top_mm: 12, right_mm: 12, bottom_mm: 14, left_mm: 12 },
            [
                {
                    id: 'TILTED_Q1',
                    type: 'mcq',
                    rotation_deg: 1,
                    x_mm: 30,
                    y_mm: 50,
                    w_mm: 30,
                    h_mm: 5,
                    questions: 1,
                    options: 4,
                    bubble_diameter_mm: 4.5,
                },
            ],
        );
        expect(missingQuestionPoints.valid).toBe(false);
        expect(
            missingQuestionPoints.warnings.some(
                (warning) => warning.code === 'tilted_points_missing',
            ),
        ).toBe(true);
    });
    it('normalizes persisted unsafe component geometry before the Stage-3 save', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (url.includes('/preview')) {
                return {
                    ok: true,
                    json: async () => ({ lock_version: 2, mapping_guides: [] }),
                };
            }
            return { ok: true, json: async () => ({ lock_version: 1 }) };
        });
        vi.stubGlobal('fetch', fetchMock);

        const draft = {
            id: 77,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'html',
            stage: 'draft',
            authoring: {
                studio: {
                    current_stage: 1,
                    stage1Mode: 'html',
                    baseType: 'html',
                    components: [
                        {
                            id: 'Examinee_ID',
                            type: 'roll',
                            title: 'Examinee Number',
                            posX: 110,
                            posY: 28,
                            w_mm: 58,
                            h_mm: 52,
                            digitsCount: 7,
                            bubbleDiameterMm: 4.5,
                            bubblePitchMm: 4.8,
                            rowPitchMm: 4.8,
                            isLocked: false,
                        },
                    ],
                    sections: [rollSection(4.8, 4.8)],
                },
            },
        };

        const { container } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: [
                { id: 1, code: 'target', version: 1, status: 'qualified' },
            ],
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/77',
                preview: '/setup/omr/studio/77/preview',
            },
        });

        const designerBubbles = container.querySelectorAll(
            '[data-omr-designer-bubble]',
        );
        expect(designerBubbles).toHaveLength(70);
        const designerRows = container.querySelector(
            '[data-omr-designer-rows]',
        ) as HTMLElement;
        const bubble = designerBubbles[0] as HTMLElement;
        const designerPitchMm =
            (Number.parseFloat(bubble.style.width) +
                Number.parseFloat(designerRows.style.gap)) /
            CANVAS_PX_PER_MM;
        const nativeBeforeTransition = nativeRollBlock(rollSection(4.8, 4.8));
        expect(
            Math.abs(
                designerPitchMm -
                    nativeBeforeTransition.option_pitch_px / PX_PER_MM,
            ),
        ).toBeLessThan(0.1);

        await fireEvent.click(
            container.querySelector('button[title="Recognition Mapping"]')!,
        );
        await waitFor(() => {
            expect(
                container.querySelector('[data-omr-stage-panel="3"]'),
            ).not.toBeNull();
        });

        const saveCall = fetchMock.mock.calls.find(([url]) =>
            String(url).includes('/studio'),
        );
        expect(saveCall).toBeDefined();
        const request = saveCall?.[1] as RequestInit;
        const payload = JSON.parse(String(request.body));
        const savedSection = payload.studio.sections.find(
            (section: { id: string }) => section.id === 'Examinee_ID',
        );
        const savedComponent = payload.studio.components.find(
            (component: { id: string }) => component.id === 'Examinee_ID',
        );
        const savedBlock = payload.mapping.blocks.find(
            (block: { name: string }) => block.name === 'Examinee_ID',
        );

        expect(savedComponent.rowPitchMm).toBe(5.76);
        expect(savedSection.rowPitchMm).toBe(5.76);
        expect(savedSection.bubblePitchMm).toBe(5.76);
        expect(savedBlock.option_pitch_px).toBe(mmToPx(5.76));
        expect(savedBlock.row_pitch_px).toBe(mmToPx(5.76));
    });
    it('discards stale Recognition Mapping sections when the rebuilt Sheet Designer has no recognition components', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (url.includes('/preview')) {
                return {
                    ok: true,
                    json: async () => ({ lock_version: 2, mapping_guides: [] }),
                };
            }
            return { ok: true, json: async () => ({ lock_version: 1 }) };
        });
        vi.stubGlobal('fetch', fetchMock);

        const { container } = render(OmrTemplateStudio, {
            draft: {
                id: 78,
                can_edit: true,
                lock_version: 0,
                marker_profile_id: 1,
                source_kind: 'html',
                stage: 'draft',
                raster_url: '/old-stage-two.png',
                authoring: {
                    studio: {
                        current_stage: 1,
                        stage1Mode: 'html',
                        baseType: 'html',
                        components: [
                            {
                                id: 'New_Header',
                                type: 'header',
                                title: 'New Sheet',
                                posX: 20,
                                posY: 20,
                                w_mm: 100,
                                h_mm: 20,
                                isLocked: false,
                            },
                        ],
                        sections: [rollSection(6.2, 6.8)],
                    },
                },
            },
            marker_profiles: [
                { id: 1, code: 'target', version: 1, status: 'qualified' },
            ],
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/78',
                preview: '/setup/omr/studio/78/preview',
            },
        });

        await fireEvent.click(
            container.querySelector('button[title=\"Recognition Mapping\"]')!,
        );
        await waitFor(() => {
            expect(
                container.querySelector('[data-omr-stage-panel=\"3\"]'),
            ).not.toBeNull();
        });

        const saveCall = fetchMock.mock.calls.find(([url]) =>
            String(url).includes('/studio/78'),
        );
        const payload = JSON.parse(String((saveCall?.[1] as RequestInit).body));
        expect(payload.studio.sections).toEqual([]);
        expect(payload.mapping.blocks).toEqual([]);
        expect(container.textContent).not.toContain(
            'Re-edit Design & Reset Raster?',
        );
    });

    it('clears a prior digital pass as soon as the Sheet Designer is rebuilt', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                json: async () => ({
                    lock_version: 1,
                    stage: 'mapping_complete',
                }),
            })),
        );

        const { container, getByText } = render(OmrTemplateStudio, {
            draft: {
                id: 79,
                can_edit: true,
                lock_version: 0,
                marker_profile_id: 1,
                source_kind: 'html',
                stage: 'digital_verified',
                digital_verified: true,
                digital_verification_current: true,
                authoring: {
                    studio: {
                        current_stage: 1,
                        stage1Mode: 'html',
                        baseType: 'html',
                        components: [],
                        sections: [],
                    },
                },
            },
            verification_run: {
                run_id: 91,
                mode: 'digital',
                status: 'passed',
                target_current: true,
                target_stale: false,
                digital_verification_current: true,
            },
            marker_profiles: [
                { id: 1, code: 'target', version: 1, status: 'qualified' },
            ],
            aptitude_areas: [],
            urls: { studio: '/setup/omr/studio/79' },
        });

        await fireEvent.click(getByText('Document Header'));
        await fireEvent.click(
            container.querySelector('button[title=\"Verification & Test\"]')!,
        );

        await waitFor(() => {
            const digitalStep = container.querySelector(
                'ol[aria-label=\"Verification progress\"] li:first-child button',
            );
            expect(digitalStep?.textContent).not.toContain('Done');
            expect(digitalStep?.textContent).toContain('Blocked');
        });
    });

    it('keeps all four fiducials visible in the designer after a canonical preview exists', () => {
        const { container } = render(StudioCanvas, {
            currentStage: 1,
            isHtmlMode: true,
            markerPattern: 'target',
            canonicalPreview: {
                artifact: {
                    canonical_url: '/canonical-page.png',
                },
            },
            showCanonicalPrintPreview: false,
            sections: [],
            components: [],
        });

        expect(container.querySelectorAll('.marker-target')).toHaveLength(4);
    });

    it('preserves entered component casing and uses write-in boxes above the examinee digits', () => {
        const components = [
            {
                id: 'Header',
                type: 'header',
                title: 'Candidate answer sheet',
                subtitle: 'First sitting',
                posX: 20,
                posY: 20,
                w_mm: 90,
                h_mm: 15,
            },
            {
                id: 'Examinee_ID',
                type: 'roll',
                title: 'Examinee number',
                digitsCount: 7,
                posX: 20,
                posY: 40,
                w_mm: 58,
                h_mm: 70,
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 5.76,
                rowPitchMm: 5.76,
            },
            {
                id: 'Candidate_Name',
                type: 'candidate_name',
                label: 'full name',
                posX: 85,
                posY: 40,
                w_mm: 80,
                h_mm: 15,
            },
            {
                id: 'Instructions',
                type: 'instructions',
                label: 'Read carefully',
                content: 'Shade one answer.',
                posX: 85,
                posY: 60,
                w_mm: 80,
                h_mm: 20,
            },
            {
                id: 'Signature',
                type: 'signature',
                title: 'Candidate signature',
                certificationText: 'I certify this sheet.',
                posX: 85,
                posY: 85,
                w_mm: 80,
                h_mm: 20,
            },
        ];
        const { container } = render(StudioCanvas, {
            currentStage: 1,
            isHtmlMode: true,
            showHeaderOverlay: false,
            showInstructionsOverlay: false,
            showSignatureOverlay: false,
            showQrOverlay: false,
            sections: [],
            components,
        });

        for (const [type, text] of [
            ['header', 'Candidate answer sheet'],
            ['roll', 'Examinee number'],
            ['candidate_name', 'full name'],
            ['instructions', 'Read carefully'],
            ['signature', 'Candidate signature'],
        ]) {
            const component = container.querySelector(
                `[data-omr-component-type="${type}"]`,
            );
            expect(component?.textContent).toContain(text);
            expect(component?.querySelector('.uppercase')).toBeNull();
        }

        const examineeNumber = container.querySelector(
            '[data-omr-component-type="roll"]',
        );
        expect(
            examineeNumber?.querySelectorAll('[data-omr-write-in-box]'),
        ).toHaveLength(7);
        expect(
            Array.from(
                examineeNumber?.querySelectorAll('[data-omr-row-label]') ?? [],
            ).map((label) => label.textContent?.trim()),
        ).toEqual(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
    });
    it('emits explicit bubble positions for single- and multi-question blocks', () => {
        const labels = ['A', 'B', 'C', 'D'];
        for (const questions of [1, 3]) {
            const optionPoints = Array.from(
                { length: questions * labels.length },
                (_, index) => ({
                    label: labels[index % labels.length],
                    x_mm: 40 + (index % labels.length) * 6,
                    y_mm: 60 + Math.floor(index / labels.length) * 7,
                }),
            );
            const result = emitNativeMapping(210, 297, [
                {
                    id: `EXPLICIT_Q${questions}`,
                    type: 'mcq',
                    x_mm: 30,
                    y_mm: 50,
                    w_mm: 32,
                    h_mm: questions * 7,
                    questions,
                    options: labels.length,
                    bubble_values: labels,
                    bubble_diameter_mm: 4.5,
                    option_points_mm: optionPoints,
                },
            ]);
            expect(result.blocks[0].bubble_positions).toHaveLength(questions);
            expect(result.blocks[0].bubble_positions?.flat()).toHaveLength(
                questions * labels.length,
            );
        }
        const optionPoints = Array.from({ length: 8 }, (_, index) => ({
            label: index % 2 === 0 ? 'A' : 'B',
            x_mm: 50 + (index % 2) * 7,
            y_mm: 70 + Math.floor(index / 2) * 8,
        }));
        const columns = emitNativeMapping(210, 297, [
            {
                id: 'TWO_COLUMNS',
                type: 'mcq',
                x_mm: 40,
                y_mm: 60,
                w_mm: 25,
                h_mm: 32,
                questions: 4,
                cols: 2,
                options: 2,
                bubble_values: ['A', 'B'],
                option_points_mm: optionPoints,
            },
        ]).blocks;
        expect(columns[0].bubble_positions).toHaveLength(2);
        expect(columns[1].bubble_positions).toHaveLength(2);
        expect(columns[0].bubble_positions?.flat()).toHaveLength(4);
        expect(columns[1].bubble_positions?.flat()).toHaveLength(4);
    });

    it('snaps progressively drifting circles while ignoring option labels above their top arcs', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const labels = ['A-B', 'A-C', 'B-C', 'C-D'];
        const optionPointsMm = Array.from({ length: 12 }, (_, index) => ({
            label: labels[index % labels.length],
            x_mm: 50 + (index % labels.length) * 8,
            y_mm: 60 + Math.floor(index / labels.length) * 10,
        }));
        const setDark = (x: number, y: number) => {
            const px = Math.round(x);
            const py = Math.round(y);
            if (px < 0 || py < 0 || px >= width || py >= height) return;
            const offset = (py * width + px) * 4;
            pixels[offset] = 0;
            pixels[offset + 1] = 0;
            pixels[offset + 2] = 0;
        };
        const expectedPoints = optionPointsMm.map((point, index) => {
            const questionIndex = Math.floor(index / labels.length);
            const centerX = Math.round((point.x_mm - questionIndex * 1.2) * scale);
            const centerY = Math.round((point.y_mm + questionIndex * 0.8) * scale);
            const radius = 2 * scale;
            for (let degrees = 0; degrees < 360; degrees += 2) {
                const angle = (degrees * Math.PI) / 180;
                for (let stroke = -0.6; stroke <= 0.6; stroke += 0.6) {
                    setDark(
                        centerX + Math.cos(angle) * (radius + stroke),
                        centerY + Math.sin(angle) * (radius + stroke),
                    );
                }
            }
            for (let x = -radius; x <= radius; x++) {
                for (let y = -2; y <= 2; y++) {
                    setDark(centerX + x, centerY - radius - 4 + y);
                }
            }
            return {
                x_mm: centerX / scale,
                y_mm: centerY / scale,
            };
        });
        const section = {
            id: 'DRIFTING_COLUMN',
            type: 'mcq',
            page_no: 1,
            posX: 45,
            posY: 55,
            questionsCount: 3,
            optionsCount: 4,
            optionLabels: labels,
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            rowPitchMm: 10,
            optionPointsMm: optionPointsMm.map((point) => ({ ...point })),
        };

        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297 },
        );

        expect(result.snappedQuestionsCount).toBe(3);
        expect(result.snappedBubblesCount).toBe(12);
        result.sections[0].optionPointsMm?.forEach((point, index) => {
            expect(point.x_mm).toBeCloseTo(expectedPoints[index].x_mm, 1);
            expect(point.y_mm).toBeCloseTo(expectedPoints[index].y_mm, 1);
        });
        const expectedPitch =
            expectedPoints.reduce((sum, point, index) => {
                if (index % labels.length === labels.length - 1) return sum;
                const next = expectedPoints[index + 1];
                return sum + Math.hypot(next.x_mm - point.x_mm, next.y_mm - point.y_mm);
            }, 0) /
            (3 * (labels.length - 1));
        expect(result.sections[0].bubblePitchMm).toBeCloseTo(expectedPitch, 2);
    });
    it('rescales explicit option points to a requested mean pitch and updates bounds', () => {
        const section = {
            questionsCount: 2,
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            optionPointsMm: [
                { label: 'A', x_mm: 10, y_mm: 20 },
                { label: 'B', x_mm: 18, y_mm: 20 },
                { label: 'C', x_mm: 27, y_mm: 30 },
                { label: 'D', x_mm: 27, y_mm: 38 },
            ],
        };

        const result = rescaleSectionOptionPointsPitch(section, 4);

        expect(result.bubblePitchMm).toBe(4);
        expect(result.optionPointsMm).toEqual([
            { label: 'A', x_mm: 10, y_mm: 20 },
            { label: 'B', x_mm: 14, y_mm: 20 },
            { label: 'C', x_mm: 27, y_mm: 30 },
            { label: 'D', x_mm: 27, y_mm: 34 },
        ]);
        expect(result.posX).toBe(8);
        expect(result.posY).toBe(18);
        expect(result.w_mm).toBe(21);
        expect(result.h_mm).toBe(18);
        expect(section.optionPointsMm[1].x_mm).toBe(18);
    });

    it('resets explicit option points to uniform row grids using the section pitch', () => {
        const result = resetSectionOptionPointsToUniformGrid({
            questionsCount: 2,
            bubbleDiameterMm: 4,
            bubblePitchMm: 6,
            rotationDeg: 0,
            optionPointsMm: [
                { label: 'A', x_mm: 10, y_mm: 20 },
                { label: 'B', x_mm: 15, y_mm: 21 },
                { label: 'C', x_mm: 30, y_mm: 40 },
                { label: 'D', x_mm: 31, y_mm: 45 },
            ],
        });

        expect(result.optionPointsMm).toEqual([
            { label: 'A', x_mm: 10, y_mm: 20 },
            { label: 'B', x_mm: 15.883, y_mm: 21.177 },
            { label: 'C', x_mm: 30, y_mm: 40 },
            { label: 'D', x_mm: 31.177, y_mm: 45.883 },
        ]);
    });

    it('preserves all programmed coordinates on a blank white page', () => {
        const width = 420;
        const height = 594;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const section = {
            id: 'BLANK_COLUMN',
            type: 'mcq',
            page_no: 1,
            questionsCount: 2,
            optionsCount: 2,
            optionLabels: ['A', 'B'],
            bubbleDiameterMm: 4,
            optionPointsMm: [
                { label: 'A', x_mm: 40, y_mm: 50 },
                { label: 'B', x_mm: 48, y_mm: 50 },
                { label: 'A', x_mm: 40, y_mm: 60 },
                { label: 'B', x_mm: 48, y_mm: 60 },
            ],
        };

        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297 },
        );

        expect(result.snappedQuestionsCount).toBe(0);
        expect(result.snappedBubblesCount).toBe(0);
        expect(result.sections[0].optionPointsMm).toEqual(section.optionPointsMm);
    });
    it('assigns competing whole-question constellations exclusively and re-searches the second row', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const paintCircle = (centerX: number, centerY: number, radii: number[], coverageDegrees = 360) => {
            for (const radius of radii) {
                for (let degrees = 0; degrees < coverageDegrees; degrees += 2) {
                    const angle = degrees * Math.PI / 180;
                    const x = Math.round(centerX + Math.cos(angle) * radius);
                    const y = Math.round(centerY + Math.sin(angle) * radius);
                    const offset = (y * width + x) * 4;
                    pixels[offset] = 0;
                    pixels[offset + 1] = 0;
                    pixels[offset + 2] = 0;
                }
            }
        };
        const optionXs = Array.from({ length: 6 }, (_, index) => 50 + index * 8);
        for (const xMm of optionXs) {
            paintCircle(xMm * scale, 60 * scale, [18, 19]);
            paintCircle(xMm * scale, 69.5 * scale, [18], 45);
        }
        const section = (id: string, ys: number[]) => ({
            id,
            type: 'mcq',
            page_no: 1,
            questionsCount: 1,
            optionsCount: 6,
            optionLabels: ['A', 'B', 'C', 'D', 'E', 'F'],
            bubbleDiameterMm: 6,
            bubblePitchMm: 8,
            rowPitchMm: 10,
            optionPointsMm: optionXs.map((x_mm, index) => ({
                label: String.fromCharCode(65 + index),
                x_mm,
                y_mm: ys[index],
            })),
        });
        const q1Points = [64.5, 63.2, 63.2, 63.2, 63.2, 63.2];
        const q2Points = [64.3, 65.5, 65.5, 65.5, 65.5, 65.5];
        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            [section('Q1', q1Points), section('Q2', q2Points)],
            {
                pageNo: 1,
                paperWidthMm: 210,
                paperHeightMm: 297,
                searchRadiusMm: 7,
                adjustDiameter: false,
            },
        );

        expect(result.snappedQuestionsCount).toBe(2);
        expect(result.snappedBubblesCount).toBe(12);
        expect(result.sections[0].optionPointsMm).toHaveLength(6);
        expect(result.sections[1].optionPointsMm).toHaveLength(6);
        for (const point of result.sections[0].optionPointsMm ?? []) {
            expect(point.y_mm).toBeCloseTo(60, 1);
        }
        for (const point of result.sections[1].optionPointsMm ?? []) {
            expect(point.y_mm).toBeCloseTo(69.5, 0);
        }
    });
    it('finds circles eight millimeters away when given an eight millimeter search radius', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const points = Array.from({ length: 6 }, (_, index) => ({
            label: String.fromCharCode(65 + index),
            x_mm: 50 + index * 8,
            y_mm: 60,
        }));
        for (const point of points) {
            const centerX = point.x_mm * scale;
            const centerY = (point.y_mm + 6) * scale;
            for (let degrees = 0; degrees < 360; degrees += 2) {
                const angle = degrees * Math.PI / 180;
                const x = Math.round(centerX + Math.cos(angle) * 12);
                const y = Math.round(centerY + Math.sin(angle) * 12);
                const offset = (y * width + x) * 4;
                pixels[offset] = 0;
                pixels[offset + 1] = 0;
                pixels[offset + 2] = 0;
            }
        }
        const section = {
            id: 'LONG_SEARCH',
            type: 'mcq',
            page_no: 1,
            questionsCount: 1,
            optionsCount: 6,
            optionLabels: points.map((point) => point.label),
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            optionPointsMm: points,
        };
        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, searchRadiusMm: 8 },
        );

        expect(result.snappedQuestionsCount).toBe(1);
        result.sections[0].optionPointsMm?.forEach((point, index) => {
            expect(point.x_mm).toBeCloseTo(points[index].x_mm, 1);
            expect(point.y_mm).toBeCloseTo(points[index].y_mm + 6, 1);
        });
    });

    it('sets absolute section tilt without cumulative drift and preserves multi-row metadata', () => {
        const section = {
            id: 'TILTED_MULTI',
            type: 'mcq',
            page_no: 2,
            questionsCount: 2,
            optionsCount: 3,
            customValuesStr: 'A, B, C',
            bubbleDiameterMm: 4,
            bubblePitchMm: 6,
            rowPitchMm: 8,
            rotationDeg: 0,
            optionPointsMm: [
                { label: 'A', x_mm: 40, y_mm: 60 },
                { label: 'B', x_mm: 46, y_mm: 60 },
                { label: 'C', x_mm: 52, y_mm: 60 },
                { label: 'A', x_mm: 40, y_mm: 68 },
                { label: 'B', x_mm: 46, y_mm: 68 },
                { label: 'C', x_mm: 52, y_mm: 68 },
            ],
            customMetadata: { retained: true },
        };
        const tiltedTwo = setSectionAbsoluteTiltDeg(section, 2);
        const tiltedThreeFive = setSectionAbsoluteTiltDeg(tiltedTwo, 3.5);
        const reset = setSectionAbsoluteTiltDeg(tiltedThreeFive, 0);

        expect(tiltedTwo.rotationDeg).toBe(2);
        expect(tiltedThreeFive.rotationDeg).toBe(3.5);
        expect(reset.rotationDeg).toBe(0);
        expect(tiltedTwo.optionPointsMm).toHaveLength(6);
        expect(tiltedThreeFive.customMetadata).toEqual({ retained: true });
        expect(reset.optionPointsMm).toHaveLength(6);
        expect(reset.optionPointsMm?.[0]).toEqual(section.optionPointsMm[0]);
        expect(reset.posX).toBeCloseTo(38, 3);
        expect(reset.h_mm).toBeCloseTo(12, 3);
    });

    it('clamps search radius and draws the requested bubble halo', () => {
        const section = searchZoneSection('CLAMP', [
            { label: 'A', x_mm: 50, y_mm: 60 },
            { label: 'B', x_mm: 58, y_mm: 60 },
        ]);
        for (const radius of [3, 5, 8]) {
            expect(buildSmartSnapSearchZoneOverlay([section], {
                pageNo: 1, activeSectionIndex: 0, searchRadiusMm: radius,
            }).radiusMm).toBe(radius);
        }
        expect(buildSmartSnapSearchZoneOverlay([section], {
            pageNo: 1, activeSectionIndex: 0, searchRadiusMm: 0,
        }).radiusMm).toBe(2);
        expect(buildSmartSnapSearchZoneOverlay([section], {
            pageNo: 1, activeSectionIndex: 0, searchRadiusMm: 20,
        }).radiusMm).toBe(12);
        const zone = buildSmartSnapSearchZoneOverlay([section], {
            pageNo: 1, activeSectionIndex: 0, searchRadiusMm: 5,
        });
        expect(zone.halos[0]).toMatchObject({ searchRadiusMm: 5, bubbleRadiusMm: 2.25 });
        expect(zone.envelopes[0]).toMatchObject({ rxMm: 7.25, heightMm: 14.5 });
    });

    it('builds tilted capsules and a rounded-rectangle fallback for irregular rows', () => {
        const tilted = searchZoneSection('TILTED', [
            { label: 'A', x_mm: 50, y_mm: 60 },
            { label: 'B', x_mm: 58, y_mm: 62 },
        ]);
        const irregular = searchZoneSection('IRREGULAR', [
            { label: 'A', x_mm: 50, y_mm: 60 },
            { label: 'B', x_mm: 58, y_mm: 60 },
            { label: 'C', x_mm: 66, y_mm: 63 },
        ]);
        const tiltedZone = buildSmartSnapSearchZoneOverlay([tilted], { pageNo: 1, activeSectionIndex: 0 });
        const irregularZone = buildSmartSnapSearchZoneOverlay([irregular], { pageNo: 1, activeSectionIndex: 0 });
        expect(tiltedZone.envelopes[0].kind).toBe('capsule');
        expect(tiltedZone.envelopes[0].angleDeg).not.toBe(0);
        expect(irregularZone.envelopes[0].kind).toBe('rect');
    });

    it('partitions multi-row sections and details only the inspected row', () => {
        const section = searchZoneSection('ROWS', [
            [{ label: 'A', x_mm: 50, y_mm: 60 }, { label: 'B', x_mm: 58, y_mm: 60 }],
            [{ label: 'A', x_mm: 50, y_mm: 70 }, { label: 'B', x_mm: 58, y_mm: 70 }],
            [{ label: 'A', x_mm: 50, y_mm: 80 }, { label: 'B', x_mm: 58, y_mm: 80 }],
        ]);
        section.questionsCount = 3;
        const zone = buildSmartSnapSearchZoneOverlay([section], {
            pageNo: 1, activeSectionIndex: 0, inspectedQuestionIndex: 1,
        });
        expect(zone.envelopes).toHaveLength(3);
        expect(zone.envelopes.map((envelope) => envelope.isDetailed)).toEqual([false, true, false]);
        expect(zone.halos).toHaveLength(2);
        expect(zone.leader).not.toBeNull();
    });

    it('shows unlocked envelopes by scope and excludes roll and locked sections', () => {
        const selected = searchZoneSection('SELECTED', [
            { label: 'A', x_mm: 40, y_mm: 60 }, { label: 'B', x_mm: 48, y_mm: 60 },
        ]);
        const other = searchZoneSection('OTHER', [
            { label: 'A', x_mm: 70, y_mm: 60 }, { label: 'B', x_mm: 78, y_mm: 60 },
        ]);
        const roll = { ...searchZoneSection('ROLL', []), type: 'roll' };
        const locked = { ...searchZoneSection('LOCKED', []), locked: true };
        const legacyLocked = { ...searchZoneSection('LEGACY_LOCKED', []), isLocked: true };
        const sections = [selected, other, roll, locked, legacyLocked];
        const zone = buildSmartSnapSearchZoneOverlay(sections, {
            pageNo: 1, activeSectionIndex: 0, scope: 'all_unlocked',
        });
        expect(zone.envelopes).toHaveLength(2);
        expect(zone.envelopes.map((envelope) => envelope.isDetailed)).toEqual([true, false]);
        expect(zone.halos).toHaveLength(2);
        expect(buildSmartSnapSearchZoneOverlay([locked], {
            pageNo: 1, activeSectionIndex: 0,
        })).toMatchObject({ selectedIsLocked: true, hasEligibleSelection: false, envelopes: [] });
        expect(buildSmartSnapSearchZoneOverlay([legacyLocked], {
            pageNo: 1, activeSectionIndex: 0,
        })).toMatchObject({ selectedIsLocked: true, hasEligibleSelection: false, envelopes: [] });
        expect(buildSmartSnapSearchZoneOverlay([roll], {
            pageNo: 1, activeSectionIndex: 0,
        }).envelopes).toHaveLength(0);
    });
    it('uses column dynamic programming to assign overlapping search disks to four stacked rows quickly', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const labels = ['A', 'B', 'C', 'D', 'E', 'F'];
        const optionPointsMm = Array.from({ length: 24 }, (_, index) => ({
            label: labels[index % labels.length],
            x_mm: 50 + (index % labels.length) * 8,
            y_mm: 60 + Math.floor(index / labels.length) * 6,
        }));
        for (const point of optionPointsMm) {
            const centerX = point.x_mm * scale;
            const centerY = (point.y_mm + 1) * scale;
            for (let degrees = 0; degrees < 360; degrees += 2) {
                const angle = degrees * Math.PI / 180;
                const x = Math.round(centerX + Math.cos(angle) * 12);
                const y = Math.round(centerY + Math.sin(angle) * 12);
                const offset = (y * width + x) * 4;
                pixels[offset] = 0;
                pixels[offset + 1] = 0;
                pixels[offset + 2] = 0;
            }
        }
        const section = {
            id: 'STACKED_ROWS',
            type: 'mcq',
            page_no: 1,
            questionsCount: 4,
            optionsCount: 6,
            optionLabels: labels,
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            rowPitchMm: 6,
            optionPointsMm,
        };
        const startedAt = performance.now();
        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, searchRadiusMm: 8, adjustDiameter: false },
        );
        const elapsedMs = performance.now() - startedAt;

        expect(elapsedMs).toBeLessThan(5000);
        expect(result.snappedQuestionsCount).toBe(4);
        expect(result.snappedBubblesCount).toBe(24);
        const snappedRows = Array.from({ length: 4 }, (_, rowIndex) =>
            result.sections[0].optionPointsMm?.[rowIndex * labels.length]?.y_mm,
        );
        expect(snappedRows).toEqual([
            expect.closeTo(61, 1),
            expect.closeTo(67, 1),
            expect.closeTo(73, 1),
            expect.closeTo(79, 1),
        ]);
        expect(snappedRows[0]).toBeLessThan(snappedRows[1]!);
        expect(snappedRows[1]).toBeLessThan(snappedRows[2]!);
        expect(snappedRows[2]).toBeLessThan(snappedRows[3]!);
    });
    it('preserves already-snapped question sections verbatim while moving a neighboring question', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const labels = ['A', 'B', 'C', 'D', 'E', 'F'];
        const optionXs = Array.from({ length: 6 }, (_, index) => 50 + index * 8);
        for (const rowY of [60, 66, 72, 78]) {
            for (const xMm of optionXs) {
                const centerX = xMm * scale;
                const centerY = rowY * scale;
                for (let degrees = 0; degrees < 360; degrees += 2) {
                    const angle = degrees * Math.PI / 180;
                    const x = Math.round(centerX + Math.cos(angle) * 12);
                    const y = Math.round(centerY + Math.sin(angle) * 12);
                    const offset = (y * width + x) * 4;
                    pixels[offset] = 0;
                    pixels[offset + 1] = 0;
                    pixels[offset + 2] = 0;
                }
            }
        }
        const makeQuestion = (id: string, startQ: number, rowY: number) => ({
            id,
            type: 'mcq',
            page_no: 1,
            startQ,
            questionsCount: 1,
            optionsCount: 6,
            optionLabels: labels,
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            rowPitchMm: 6,
            posX: 48,
            posY: rowY - 2,
            w_mm: 44,
            h_mm: 4,
            optionPointsMm: optionXs.map((x_mm, index) => ({
                label: labels[index],
                x_mm,
                y_mm: rowY,
            })),
        });
        const originalQuestions = [
            makeQuestion('Q1', 27, 60),
            makeQuestion('Q2', 28, 66),
            makeQuestion('Q3', 29, 72),
            makeQuestion('Q30', 30, 70),
        ];
        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            originalQuestions,
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, searchRadiusMm: 8 },
        );

        expect(result.snappedQuestionsCount).toBe(4);
        expect(result.sections.slice(0, 3)).toEqual(originalQuestions.slice(0, 3));
        for (const point of result.sections[3].optionPointsMm ?? []) {
            expect(point.y_mm).toBeCloseTo(78, 1);
        }
    });

    it('requires both S and D circles instead of snapping to one circle plus a smudge', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const createPage = () => {
            const pixels = new Uint8ClampedArray(width * height * 4);
            for (let index = 0; index < pixels.length; index += 4) {
                pixels[index] = 255;
                pixels[index + 1] = 255;
                pixels[index + 2] = 255;
                pixels[index + 3] = 255;
            }
            return pixels;
        };
        const darkPixel = (pixels: Uint8ClampedArray, x: number, y: number) => {
            const offset = (y * width + x) * 4;
            pixels[offset] = 0;
            pixels[offset + 1] = 0;
            pixels[offset + 2] = 0;
        };
        const paintCircle = (pixels: Uint8ClampedArray, centerX: number, centerY: number) => {
            for (let degrees = 0; degrees < 360; degrees += 2) {
                const angle = degrees * Math.PI / 180;
                darkPixel(
                    pixels,
                    Math.round(centerX + Math.cos(angle) * 12),
                    Math.round(centerY + Math.sin(angle) * 12),
                );
            }
        };
        const paintInteriorLetter = (pixels: Uint8ClampedArray, centerX: number, centerY: number) => {
            for (let step = -4; step <= 4; step++) {
                darkPixel(pixels, centerX + step, centerY);
                darkPixel(pixels, centerX, centerY + step);
            }
        };
        const section = {
            id: 'SD_MIN_SUPPORT',
            type: 'mcq',
            page_no: 1,
            startQ: 1,
            questionsCount: 1,
            optionsCount: 2,
            optionLabels: ['S', 'D'],
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            rowPitchMm: 6,
            optionPointsMm: [
                { label: 'S', x_mm: 50, y_mm: 60 },
                { label: 'D', x_mm: 58, y_mm: 60 },
            ],
        };
        const incompletePixels = createPage();
        paintCircle(incompletePixels, 50 * scale, 60 * scale);
        for (let offset = -7; offset <= 7; offset++) {
            darkPixel(incompletePixels, 58 * scale + offset, 60 * scale + (offset % 3));
        }
        const incomplete = snapSectionsToPrintedCircles(
            { data: incompletePixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, adjustDiameter: false },
        );

        expect(incomplete.snappedQuestionsCount).toBe(0);
        expect(incomplete.sections[0].optionPointsMm).toEqual(section.optionPointsMm);

        const completePixels = createPage();
        for (const xMm of [50, 58]) {
            paintCircle(completePixels, xMm * scale, 60 * scale);
            paintInteriorLetter(completePixels, xMm * scale, 60 * scale);
        }
        const complete = snapSectionsToPrintedCircles(
            { data: completePixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, adjustDiameter: false },
        );

        expect(complete.snappedQuestionsCount).toBe(1);
        expect(complete.snappedBubblesCount).toBe(2);
        expect(complete.sections[0].optionPointsMm).toEqual(section.optionPointsMm);
    });
    it('rejects solid filled disks and squares near programmed question bubbles', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const pixels = new Uint8ClampedArray(width * height * 4);
        for (let index = 0; index < pixels.length; index += 4) {
            pixels[index] = 255;
            pixels[index + 1] = 255;
            pixels[index + 2] = 255;
            pixels[index + 3] = 255;
        }
        const darkPixel = (x: number, y: number) => {
            const offset = (y * width + x) * 4;
            pixels[offset] = 0;
            pixels[offset + 1] = 0;
            pixels[offset + 2] = 0;
        };
        const optionPointsMm = Array.from({ length: 4 }, (_, index) => ({
            label: String.fromCharCode(65 + index),
            x_mm: 50 + index * 8,
            y_mm: 60,
        }));
        for (const [index, point] of optionPointsMm.entries()) {
            const centerX = point.x_mm * scale;
            const centerY = point.y_mm * scale;
            if (index % 2 === 0) {
                for (let y = -12; y <= 12; y++) {
                    for (let x = -12; x <= 12; x++) {
                        if (x * x + y * y <= 12 * 12) darkPixel(centerX + x, centerY + y);
                    }
                }
            } else {
                for (let y = -12; y <= 12; y++) {
                    for (let x = -12; x <= 12; x++) darkPixel(centerX + x, centerY + y);
                }
            }
        }
        const section = {
            id: 'SOLID_OBJECTS',
            type: 'mcq',
            page_no: 1,
            questionsCount: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
            bubbleDiameterMm: 4,
            bubblePitchMm: 8,
            optionPointsMm,
        };
        const result = snapSectionsToPrintedCircles(
            { data: pixels, width, height },
            [section],
            { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, searchRadiusMm: 5 },
        );

        expect(result.snappedQuestionsCount).toBe(0);
        expect(result.sections[0].optionPointsMm).toEqual(optionPointsMm);
    });

    it('discovers unknown printed circle radii and propagates the learned size to the next row', () => {
        const scale = 6;
        const width = 210 * scale;
        const height = 297 * scale;
        const labels = ['S', 'D', 'A', 'B'];
        for (const printedScale of [0.82, 1.18]) {
            const pixels = new Uint8ClampedArray(width * height * 4);
            for (let index = 0; index < pixels.length; index += 4) {
                pixels[index] = 255;
                pixels[index + 1] = 255;
                pixels[index + 2] = 255;
                pixels[index + 3] = 255;
            }
            const optionPointsMm = Array.from({ length: 8 }, (_, index) => ({
                label: labels[index % labels.length],
                x_mm: 50 + (index % labels.length) * 8,
                y_mm: 60 + Math.floor(index / labels.length) * 9,
            }));
            for (let row = 0; row < 2; row++) {
                for (let option = 0; option < labels.length; option++) {
                    const centerX = optionPointsMm[row * labels.length + option].x_mm * scale;
                    const centerY = (60 + row * 10) * scale;
                    const radius = 2 * scale * printedScale;
                    for (let degrees = 0; degrees < 360; degrees += 2) {
                        const angle = degrees * Math.PI / 180;
                        const x = Math.round(centerX + Math.cos(angle) * radius);
                        const y = Math.round(centerY + Math.sin(angle) * radius);
                        const offset = (y * width + x) * 4;
                        pixels[offset] = 0;
                        pixels[offset + 1] = 0;
                        pixels[offset + 2] = 0;
                    }
                }
            }
            const section = {
                id: `UNKNOWN_DIAMETER_${printedScale}`,
                type: 'mcq',
                page_no: 1,
                questionsCount: 2,
                optionsCount: labels.length,
                optionLabels: labels,
                bubbleDiameterMm: 4,
                bubblePitchMm: 8,
                rowPitchMm: 9,
                optionPointsMm,
            };
            const result = snapSectionsToPrintedCircles(
                { data: pixels, width, height },
                [section],
                { pageNo: 1, paperWidthMm: 210, paperHeightMm: 297, minBubbleMm: 2.5 },
            );

            expect(result.snappedQuestionsCount).toBe(2);
            expect(result.sections[0].bubbleDiameterMm).toBeCloseTo(4 * printedScale, 1);
            result.sections[0].optionPointsMm?.forEach((point, index) => {
                const row = Math.floor(index / labels.length);
                expect(point.y_mm).toBeCloseTo(60 + row * 10, 1);
            });
        }
    });
});
