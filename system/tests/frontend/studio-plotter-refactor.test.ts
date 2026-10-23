// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import StudioBubblePanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioBubblePanel.svelte';
import StudioCanvas from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioCanvas.svelte';

describe('Studio Stage 2 Plotter Refactor & Mode Isolation', () => {
    afterEach(() => {
        cleanup();
    });

    const sampleAptitudes = [
        { id: 1, code: 'LOGIC', name: 'General Ability', max_items: 25 },
        { id: 2, code: 'VERBAL', name: 'Verbal Ability', max_items: 25 },
    ];

    it('renders Question Answer Bubbles section with collapse toggle and flexible options count input', async () => {
        let plottingConfig: Record<string, unknown> = {
            active: false,
            areaCode: 'LOGIC',
            itemNumber: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
            bubbleDiameterMm: 4.5,
        };

        const { container, getByLabelText, queryByLabelText } = render(StudioBubblePanel, {
            sections: [],
            activeSectionIndex: -1,
            aptitudeAreas: sampleAptitudes,
            isUploadedSource: true,
            plottingConfig,
        });

        // 1. Clear Section Header
        const heading = container.querySelector('[data-omr-question-bubbles-heading="true"]');
        expect(heading).not.toBeNull();
        expect(heading?.textContent).toContain('Question Answer Bubbles');

        // 2. Collapse Toggle
        const collapseBtn = container.querySelector('[data-omr-question-bubbles-collapse="true"]') as HTMLButtonElement;
        expect(collapseBtn).not.toBeNull();

        // 3. Flexible Option Count Stepper/Input (2-10)
        const optCountInput = container.querySelector('[data-omr-plotter-options-count-input="true"]') as HTMLInputElement;
        expect(optCountInput).not.toBeNull();
        expect(optCountInput.type).toBe('number');
        expect(Number(optCountInput.min)).toBe(2);
        expect(Number(optCountInput.max)).toBe(10);

        // 4. 2-Click Span Switcher (role="switch")
        const spanSwitch = container.querySelector('[data-omr-question-span-switch="true"]') as HTMLButtonElement;
        expect(spanSwitch).not.toBeNull();
        expect(spanSwitch.getAttribute('role')).toBe('switch');

        // 5. Info icon tooltip
        const infoIcon = container.querySelector('[data-omr-plotter-info-icon="true"]');
        expect(infoIcon).not.toBeNull();
    });

    it('bidirectionally mutates active selected section when changing Aptitude or Item #', async () => {
        let sections: Array<Record<string, unknown>> = [
            {
                id: 'sec_1',
                type: 'mcq',
                area_code: 'LOGIC',
                aptitudeAreaCode: 'LOGIC',
                startQ: 1,
                questionsCount: 1,
                name: 'LOGIC · Q1',
                page_no: 1,
                optionLabels: ['A', 'B', 'C', 'D'],
                optionsCount: 4,
                _testMarker: 'INITIAL',
            },
        ];
        let activeSectionIndex = 0;
        let plottingConfig: Record<string, unknown> = {
            active: false,
            areaCode: 'LOGIC',
            itemNumber: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
        };

        const { container } = render(StudioBubblePanel, {
            sections,
            activeSectionIndex,
            aptitudeAreas: sampleAptitudes,
            isUploadedSource: true,
            plottingConfig,
        });

        // Changing Aptitude Area in the plotter updates activeSection.area_code
        const areaSelect = container.querySelector('[data-omr-plotter-area-select="true"]') as HTMLSelectElement;
        expect(areaSelect).not.toBeNull();

        await fireEvent.change(areaSelect, { target: { value: 'VERBAL' } });
        expect(sections[0].area_code).toBe('VERBAL');
        expect(sections[0].name).toBe('VERBAL · Q1');

        // Changing Item Number in the plotter updates activeSection.startQ
        const itemNumberInput = container.querySelector('[data-omr-plotter-item-number="true"]') as HTMLInputElement;
        expect(itemNumberInput).not.toBeNull();
        await fireEvent.input(itemNumberInput, { target: { value: '15' } });
        const chip = container.querySelector('[data-omr-solid-item-chip="sec_1"]');
        expect(chip?.textContent).toContain('VERBAL');
        expect(chip?.textContent).toContain('Q15');
    });

    it('groups plotted bubble items by Aptitude Area in collapsible accordions', async () => {
        const sections: Array<Record<string, unknown>> = [
            { id: 'q1', type: 'mcq', area_code: 'LOGIC', startQ: 1, questionsCount: 1, page_no: 1 },
            { id: 'q2', type: 'mcq', area_code: 'LOGIC', startQ: 2, questionsCount: 1, page_no: 1 },
            { id: 'q3', type: 'mcq', area_code: 'VERBAL', startQ: 1, questionsCount: 1, page_no: 1 },
        ];

        const { container } = render(StudioBubblePanel, {
            sections,
            activeSectionIndex: -1,
            aptitudeAreas: sampleAptitudes,
            isUploadedSource: true,
            plottingConfig: { active: false },
        });

        // Group headers
        const logicGroup = container.querySelector('[data-omr-aptitude-group="LOGIC"]');
        expect(logicGroup).not.toBeNull();
        expect(logicGroup?.textContent).toContain('2 items');

        const verbalGroup = container.querySelector('[data-omr-aptitude-group="VERBAL"]');
        expect(verbalGroup).not.toBeNull();
        expect(verbalGroup?.textContent).toContain('1 item');
    });

    it('strictly isolates Applicant Number digit plotting from question bubble plotting', async () => {
        let plottingConfig: Record<string, unknown> = {
            active: true,
            targetType: 'applicant_digit',
            mode: 'applicant_digit',
            digitSpanMode: true,
            pointsMm: [{ label: '0', x: 50, y: 50 }],
            optionsCount: 10,
            optionLabels: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            bubbleDiameterMm: 4.5,
            digitCount: 6,
        };
        let sections: Array<Record<string, unknown>> = [];

        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/test.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections,
            activeSectionIndex: -1,
            plottingConfig,
        });

        // The floating plotting banner should show '#' for applicant digit, NEVER 'A' or 'B'
        const banner = container.querySelector('[data-omr-plotting-banner="true"]');
        expect(banner).not.toBeNull();
        expect(banner?.textContent).toContain('#');
        expect(banner?.textContent).not.toContain('Option A');
    });

    it('cancels question plotting when initiating digit plotting and vice versa', async () => {
        let plottingConfig: Record<string, unknown> = {
            active: true,
            mode: 'consecutive',
            targetType: undefined,
            areaCode: 'LOGIC',
            itemNumber: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
        };
        let identity = { method: 'shaded_grid', digit_count: 6 };

        const { container } = render(StudioBubblePanel, {
            sections: [],
            activeSectionIndex: -1,
            aptitudeAreas: sampleAptitudes,
            isUploadedSource: true,
            identity,
            plottingConfig,
        });

        // Clicking "Plot Digit 1" button in the Applicant Number section
        const digitPlotBtn = container.querySelector('[data-omr-start-digit-plot="true"]') as HTMLButtonElement;
        expect(digitPlotBtn).not.toBeNull();

        await fireEvent.click(digitPlotBtn);

        // Must now be applicant_digit targetType, not question
        expect(plottingConfig.active).toBe(true);
        expect(plottingConfig.targetType).toBe('applicant_digit');
        expect(plottingConfig.mode).toBe('applicant_digit');

        // Now click "Plot Question" button
        const plotQuestionBtn = container.querySelector('[data-omr-start-plot="true"]') as HTMLButtonElement;
        expect(plotQuestionBtn).not.toBeNull();

        await fireEvent.click(plotQuestionBtn);

        // Must now be question item plotting, not applicant_digit
        expect(plottingConfig.active).toBe(true);
        expect(plottingConfig.targetType).toBeUndefined();
        expect(plottingConfig.mode).toBe('consecutive');
    });

    it('dynamically adjusts plotted Applicant Number grid columns and dimensions when digit count is changed from 6 to 7', async () => {
        let sections: Array<Record<string, unknown>> = [
            {
                id: 'Applicant_Number_Page_1',
                name: 'Applicant Number · Page 1',
                type: 'roll',
                identity_grid: true,
                page_no: 1,
                rollDirection: 'vertical',
                questionsCount: 6,
                posX: 110,
                posY: 28,
                w_mm: 32.0,
                h_mm: 47.7,
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 5.5,
                rowPitchMm: 4.8,
            },
        ];
        let identity = { method: 'shaded_grid', digit_count: 6 };

        const { container } = render(StudioBubblePanel, {
            sections,
            activeSectionIndex: 0,
            aptitudeAreas: sampleAptitudes,
            isUploadedSource: true,
            identity,
            onIdentityChanged: (next: { method: string; digit_count: number }) => {
                identity = next;
            },
            plottingConfig: { active: false },
        });

        // 1. Check initial digit count input in the Applicant Number section
        const digitCountInput = container.querySelector('[aria-label="Applicant Number grid digit count"]') as HTMLInputElement;
        expect(digitCountInput).not.toBeNull();
        expect(Number(digitCountInput.value)).toBe(6);

        // 2. Change digit count from 6 to 7
        await fireEvent.input(digitCountInput, { target: { value: '7' } });

        // 3. Section in sections array must immediately update questionsCount to 7 and recalculate w_mm
        expect(sections[0].questionsCount).toBe(7);
        // For 7 columns with bubbleDiameter 4.5 and pitch 5.5: 4.5 + (7 - 1) * 5.5 = 37.5 mm
        expect(sections[0].w_mm).toBe(37.5);
        expect(identity.digit_count).toBe(7);

        // 4. Check that Total Digits in generator card synchronized to 7
        const multCountInput = container.querySelector('[data-omr-digit-mult-count="true"]') as HTMLInputElement;
        expect(multCountInput).not.toBeNull();
        expect(Number(multCountInput.value)).toBe(7);

        // 5. Change digit count via the Total Digits input in the generator card
        await fireEvent.input(multCountInput, { target: { value: '8' } });

        // 6. Both inputs and identity must immediately update to 8
        expect(Number(multCountInput.value)).toBe(8);
        expect(Number(digitCountInput.value)).toBe(8);
        expect(identity.digit_count).toBe(8);
    });

    it('renders 7 columns of applicant digit bubbles on the canvas when questionsCount is 7', async () => {
        let sections: Array<Record<string, unknown>> = [
            {
                id: 'Applicant_Number_Page_1',
                name: 'Applicant Number · Page 1',
                type: 'roll',
                identity_grid: true,
                page_no: 1,
                rollDirection: 'vertical',
                questionsCount: 7,
                posX: 110,
                posY: 28,
                w_mm: 37.5,
                h_mm: 47.7,
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 5.5,
                rowPitchMm: 4.8,
            },
        ];

        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/test.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections,
            activeSectionIndex: 0,
            plottingConfig: { active: false },
        });

        // 7 columns of 10 digits (0..9) = 70 recognition bubbles
        const bubbles = container.querySelectorAll('[data-omr-recognition-bubble="true"]');
        expect(bubbles.length).toBe(70);
    });

    it('renders both the 0-9 bubble grid and the draggable handwritten box simultaneously when dual mode is active', async () => {
        let sections: Array<Record<string, unknown>> = [
            {
                id: 'Applicant_Number_Page_1',
                name: 'Applicant Number · Page 1',
                type: 'roll',
                identity_grid: true,
                page_no: 1,
                rollDirection: 'vertical',
                questionsCount: 7,
                posX: 110,
                posY: 28,
                w_mm: 37.5,
                h_mm: 47.7,
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 5.5,
                rowPitchMm: 4.8,
            },
        ];

        const identity = {
            strategy: 'bubbles_and_write_in',
            has_bubbles: true,
            has_write_in: true,
            digit_count: 7,
            write_in_boxes: [
                { page_no: 1, x_mm: 110, y_mm: 18, w_mm: 42, h_mm: 8.5 },
            ],
        };

        const { container } = render(StudioCanvas, {
            isHtmlMode: false,
            rasterUrl: '/test.jpg',
            currentStage: 3,
            activePageNo: 1,
            sections,
            activeSectionIndex: 0,
            plottingConfig: { active: false },
            identity,
        });

        // 1. Both recognition bubbles (70) and handwritten review box must exist on the canvas
        const bubbles = container.querySelectorAll('[data-omr-recognition-bubble="true"]');
        expect(bubbles.length).toBe(70);

        const writeInBox = container.querySelector('[data-omr-write-in-box="true"]');
        expect(writeInBox).not.toBeNull();
        expect(writeInBox?.textContent).toContain('Applicant Number (Handwritten Box)');
        expect(writeInBox?.textContent).toContain('42×9mm');
    });
});
