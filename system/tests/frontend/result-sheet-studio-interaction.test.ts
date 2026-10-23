// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';

describe('ResultSheetStudio Interaction & Marquee Contract', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    const mockCatalog = {
        header: { label: 'Header', description: 'Institution header' },
        field: { label: 'Field', description: 'Dynamic applicant field' },
        score_table: {
            label: 'Score Table',
            description: 'Scores and subjects',
        },
    };

    const sampleDocument = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                locked: false,
                props: { title: 'State University' },
            },
            {
                id: 'field-1',
                type: 'field',
                x: 10,
                y: 40,
                w: 80,
                h: 12,
                locked: false,
                props: {
                    label: 'Applicant No',
                    binding: 'applicant_reference',
                },
            },
            {
                id: 'field-2',
                type: 'field',
                x: 100,
                y: 40,
                w: 80,
                h: 12,
                locked: true,
                props: { label: 'Program Choice', binding: 'program_choice' },
            },
        ],
    };
    function setPageRect(node: HTMLElement) {
        vi.spyOn(node, 'getBoundingClientRect').mockReturnValue({
            left: 0,
            top: 0,
            width: 1000,
            height: 1000,
        } as DOMRect);
    }

    it('renders components with accurate data attributes and tool controls', () => {
        const { container, getByTitle } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        );
        const field1Btn = container.querySelector(
            '[data-result-sheet-component="field-1"]',
        );
        const field2Btn = container.querySelector(
            '[data-result-sheet-component="field-2"]',
        );

        expect(headerBtn).not.toBeNull();
        expect(field1Btn).not.toBeNull();
        expect(field2Btn).not.toBeNull();

        // Tool switcher buttons exist
        expect(getByTitle('Select & Marquee tool (V)')).not.toBeNull();
        expect(getByTitle('Pan / Hand tool (H or Space+Drag)')).not.toBeNull();
    });

    it('adopts a converted builder document after mounting from defaults and preserves it on commit', async () => {
        const convertedDocument = {
            ...sampleDocument,
            page: {
                paper_size: 'legal',
                orientation: 'landscape',
                width_mm: 356,
                height_mm: 216,
                margin_mm: 18,
            },
            components: [
                {
                    ...sampleDocument.components[0],
                    id: 'converted-header',
                    x: 24,
                    y: 20,
                    w: 280,
                    h: 28,
                },
            ],
        };
        const emitted: unknown[] = [];
        const { container, rerender } = render(ResultSheetStudio, {
            builderDocument: null,
            builderDefaults: sampleDocument,
            builderCatalog: mockCatalog,
            onChange: (document: unknown) => emitted.push(document),
        });

        const defaultHeader = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        await fireEvent.pointerDown(defaultHeader, { button: 0 });
        expect(defaultHeader.getAttribute('aria-pressed')).toBe('true');

        await rerender({ builderDocument: convertedDocument });
        await tick();

        const convertedPage = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        expect(convertedPage.style.width).toBe('356mm');
        expect(convertedPage.style.height).toBe('216mm');
        expect(convertedPage.style.padding).toBe('18mm');
        expect(
            container.querySelector('[data-result-sheet-component="header-1"]'),
        ).toBeNull();

        const convertedHeader = container.querySelector(
            '[data-result-sheet-component="converted-header"]',
        ) as HTMLElement;
        expect(convertedHeader).not.toBeNull();
        expect(convertedHeader.getAttribute('aria-pressed')).toBe('false');

        await fireEvent.pointerDown(convertedHeader, { button: 0 });
        await fireEvent.keyDown(
            container.querySelector('[data-editor-surface]') as HTMLElement,
            { key: 'ArrowRight' },
        );

        const committed = emitted.at(-1) as {
            page?: Record<string, unknown>;
            components?: Array<Record<string, unknown>>;
        };
        expect(committed.page).toMatchObject({
            paper_size: 'legal',
            orientation: 'landscape',
            width_mm: 356,
            height_mm: 216,
            margin_mm: 18,
        });
        expect(committed.components).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: 'converted-header',
                    x: 25,
                    w: 280,
                    h: 28,
                }),
            ]),
        );
    });

    it('selects single component on click and toggles selection with Shift+Click', async () => {
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const field1Btn = container.querySelector(
            '[data-result-sheet-component="field-1"]',
        ) as HTMLElement;

        // Single click selects header
        await fireEvent.pointerDown(headerBtn, { button: 0 });
        expect(headerBtn.getAttribute('aria-pressed')).toBe('true');
        expect(field1Btn.getAttribute('aria-pressed')).toBe('false');

        // Shift + click on field1 adds it to selection (multi-select)
        await fireEvent.pointerDown(field1Btn, { button: 0, shiftKey: true });
        expect(headerBtn.getAttribute('aria-pressed')).toBe('true');
        expect(field1Btn.getAttribute('aria-pressed')).toBe('true');
    });

    it('switches tool modes between Select (V) and Pan (H)', async () => {
        const { getByTitle, container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const selectToolBtn = getByTitle('Select & Marquee tool (V)');
        const panToolBtn = getByTitle('Pan / Hand tool (H or Space+Drag)');
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;

        // Click Pan tool
        await fireEvent.click(panToolBtn);
        expect(panToolBtn.className).toContain('bg-studio-selection');

        // Click Select tool
        await fireEvent.click(selectToolBtn);
        expect(selectToolBtn.className).toContain('bg-studio-selection');

        // Shortcut H switches to Pan tool when focused in editor
        await fireEvent.keyDown(editorSurface, { key: 'h' });
        expect(panToolBtn.className).toContain('bg-studio-selection');

        // Shortcut V switches back to Select tool when focused in editor
        await fireEvent.keyDown(editorSurface, { key: 'v' });
        expect(selectToolBtn.className).toContain('bg-studio-selection');
    });
    it('keeps temporary Space pan active across pointerleave until keyup', async () => {
        const { getByTitle, container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        const panToolBtn = getByTitle('Pan / Hand tool (H or Space+Drag)');
        const pageSurface = page.parentElement as HTMLElement;
        const initialTransform = pageSurface.style.transform;

        await fireEvent.pointerEnter(canvas);
        await fireEvent.keyDown(document.body, { key: ' ', code: 'Space' });
        await tick();
        expect(canvas.className).toContain('cursor-grab');

        await fireEvent.pointerLeave(canvas);
        await tick();
        expect(canvas.className).toContain('cursor-grab');

        await fireEvent.pointerEnter(canvas);
        await tick();
        expect(canvas.className).toContain('cursor-grab');

        await fireEvent.pointerDown(canvas, {
            button: 0,
            buttons: 1,
            pointerId: 19,
            clientX: 100,
            clientY: 120,
        });
        await fireEvent.pointerMove(window, {
            pointerId: 19,
            clientX: 132,
            clientY: 151,
        });
        await tick();

        expect(pageSurface.style.transform).not.toBe(initialTransform);
        expect(pageSurface.style.transform).toContain('translate(32px,31px)');

        await fireEvent.pointerUp(window, {
            pointerId: 19,
            clientX: 132,
            clientY: 151,
        });
        await fireEvent.keyUp(document.body, { key: ' ', code: 'Space' });
        await tick();

        expect(canvas.className).toContain('cursor-crosshair');
        expect(canvas.className).not.toContain('cursor-grab');
        expect(panToolBtn.className).not.toContain('bg-studio-selection');
    });

    it('clears temporary Space pan on Escape and visibility changes', async () => {
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;

        await fireEvent.pointerEnter(canvas);
        await fireEvent.keyDown(document.body, { key: ' ', code: 'Space' });
        await tick();
        expect(canvas.className).toContain('cursor-grab');

        await fireEvent.keyDown(editorSurface, {
            key: 'Escape',
            code: 'Escape',
        });
        await tick();
        expect(canvas.className).toContain('cursor-crosshair');

        await fireEvent.keyDown(document.body, { key: ' ', code: 'Space' });
        await tick();
        expect(canvas.className).toContain('cursor-grab');

        document.dispatchEvent(new Event('visibilitychange'));
        await tick();
        expect(canvas.className).toContain('cursor-crosshair');
    });

    it('clears selection on Escape when no gesture is active', async () => {
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;
        await fireEvent.pointerDown(headerBtn, { button: 0 });
        expect(headerBtn.getAttribute('aria-pressed')).toBe('true');

        await fireEvent.keyDown(editorSurface, { key: 'Escape' });
        expect(headerBtn.getAttribute('aria-pressed')).toBe('false');
    });

    it('selects all components with Mod+A inside editor surface', async () => {
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const field1Btn = container.querySelector(
            '[data-result-sheet-component="field-1"]',
        ) as HTMLElement;
        const field2Btn = container.querySelector(
            '[data-result-sheet-component="field-2"]',
        ) as HTMLElement;
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;

        await fireEvent.keyDown(editorSurface, { key: 'a', ctrlKey: true });

        expect(headerBtn.getAttribute('aria-pressed')).toBe('true');
        expect(field1Btn.getAttribute('aria-pressed')).toBe('true');
        expect(field2Btn.getAttribute('aria-pressed')).toBe('true');
    });

    // Defect 1: Regression test for keyboard layer/nudge paths committing full document
    it('nudge and layer shortcuts commit valid document structure without erasing page or other components', async () => {
        let lastEmitted: {
            page?: { paper_size: string };
            components?: Array<{ x: number }>;
        } | null = null;
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
            onChange: (doc: unknown) => {
                lastEmitted = doc as {
                    page?: { paper_size: string };
                    components?: Array<{ x: number }>;
                };
            },
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;

        // Select header
        await fireEvent.pointerDown(headerBtn, { button: 0 });

        // Nudge right with arrow key
        await fireEvent.keyDown(editorSurface, { key: 'ArrowRight' });

        expect(lastEmitted).not.toBeNull();
        expect(lastEmitted.page).toBeDefined();
        expect(lastEmitted.page.paper_size).toBe('a4');
        expect(Array.isArray(lastEmitted.components)).toBe(true);
        expect(lastEmitted.components.length).toBe(3);
        expect(lastEmitted.components[0].x).toBe(11); // 10 + 1

        // Layer shortcut Ctrl+] (move up)
        await fireEvent.keyDown(editorSurface, { key: ']', ctrlKey: true });
        expect(lastEmitted.components.length).toBe(3);
        expect(lastEmitted.page).toBeDefined();

        // Ctrl+Shift+] is reported as "}" by keyboard layouts and must still
        // bring the selected layer to the front.
        await fireEvent.keyDown(editorSurface, {
            key: '}',
            code: 'BracketRight',
            ctrlKey: true,
            shiftKey: true,
        });
        expect(lastEmitted.components.map((component) => component.id)).toEqual(
            ['field-1', 'field-2', 'header-1'],
        );

        // Ctrl+Shift+[ is reported as "{" and must still send it to the back.
        await fireEvent.keyDown(editorSurface, {
            key: '{',
            code: 'BracketLeft',
            ctrlKey: true,
            shiftKey: true,
        });
        expect(lastEmitted.components.map((component) => component.id)).toEqual(
            ['header-1', 'field-1', 'field-2'],
        );
    });

    // Defect 3: Locked selected items excluded from layer reorder mutations
    it('excludes locked items from layer reorder shortcuts', async () => {
        let lastEmitted: unknown = null;
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
            onChange: (doc: unknown) => {
                lastEmitted = doc;
            },
        });

        const field2Btn = container.querySelector(
            '[data-result-sheet-component="field-2"]',
        ) as HTMLElement;
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;

        // Select locked field-2
        await fireEvent.pointerDown(field2Btn, { button: 0 });

        // Try layer step down on locked item
        lastEmitted = null;
        await fireEvent.keyDown(editorSurface, { key: '[', ctrlKey: true });

        // Should NOT have mutated layer order for locked item

        expect(lastEmitted).toBeNull();
    });
    it('normalizes brace layer shortcuts while retaining input, IME, and repeat guards', async () => {
        let emitted = 0;
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
            onChange: () => {
                emitted += 1;
            },
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const editorSurface = container.querySelector(
            '[data-editor-surface]',
        ) as HTMLElement;
        await fireEvent.pointerDown(headerBtn, { button: 0 });
        emitted = 0;

        const input = document.createElement('input');
        editorSurface.append(input);
        await fireEvent.keyDown(input, {
            key: '}',
            code: 'BracketRight',
            ctrlKey: true,
            shiftKey: true,
        });
        expect(emitted).toBe(0);
        input.remove();

        await fireEvent.keyDown(editorSurface, {
            key: '}',
            code: 'BracketRight',
            ctrlKey: true,
            shiftKey: true,
            repeat: true,
        });
        expect(emitted).toBe(0);

        await fireEvent.keyDown(editorSurface, {
            key: '}',
            code: 'BracketRight',
            ctrlKey: true,
            shiftKey: true,
            isComposing: true,
        });
        expect(emitted).toBe(0);

        await fireEvent.keyDown(editorSurface, {
            key: '}',
            code: 'BracketRight',
            ctrlKey: true,
            shiftKey: true,
        });
        expect(emitted).toBe(1);
    });

    // Defect 4: Shortcuts ignore events originating outside editor surface
    it('does not trigger shortcuts when event originates outside [data-editor-surface]', async () => {
        let lastEmitted: unknown = null;
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
            onChange: (doc: unknown) => {
                lastEmitted = doc;
            },
        });

        const headerBtn = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        await fireEvent.pointerDown(headerBtn, { button: 0 });

        // Outside element
        const outsideEl = document.createElement('button');
        document.body.appendChild(outsideEl);

        // Fire delete shortcut from outside element
        await fireEvent.keyDown(outsideEl, { key: 'Delete' });

        // Should not have deleted component
        expect(lastEmitted).toBeNull();

        document.body.removeChild(outsideEl);
    });
    it('restores the pre-duplicate selection when an Alt-drag is cancelled', async () => {
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;
        const header = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const field1 = container.querySelector(
            '[data-result-sheet-component="field-1"]',
        ) as HTMLElement;
        setPageRect(page);

        await fireEvent.pointerDown(header, {
            button: 0,
            pointerId: 10,
            clientX: 100,
            clientY: 100,
        });
        await fireEvent.pointerDown(field1, {
            button: 0,
            pointerId: 11,
            shiftKey: true,
            clientX: 100,
            clientY: 200,
        });
        await tick();
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');

        await fireEvent.pointerDown(header, {
            button: 0,
            pointerId: 12,
            altKey: true,
            clientX: 100,
            clientY: 100,
        });
        await fireEvent.pointerMove(window, {
            pointerId: 12,
            clientX: 180,
            clientY: 260,
        });
        expect(
            container.querySelectorAll('[data-result-sheet-component]').length,
        ).toBe(5);

        await fireEvent.pointerCancel(canvas, { pointerId: 12 });
        await tick();

        expect(
            container.querySelectorAll('[data-result-sheet-component]').length,
        ).toBe(3);
        expect(header.getAttribute('aria-pressed')).toBe('true');
        expect(field1.getAttribute('aria-pressed')).toBe('true');
        expect(field1.className).toContain('ring-offset-studio-sheet');
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');
    });

    it('opens the contextual Inspector only after marquee completion', async () => {
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
        });

        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;
        const header = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        setPageRect(page);

        expect(inspector.dataset.resultSheetPanelState).toBe('closed');
        await fireEvent.pointerDown(canvas, {
            button: 0,
            pointerId: 20,
            clientX: 0,
            clientY: 0,
        });
        await fireEvent.pointerMove(window, {
            pointerId: 20,
            clientX: 150,
            clientY: 150,
        });
        await tick();

        expect(inspector.dataset.resultSheetPanelState).toBe('closed');
        expect(header.getAttribute('aria-pressed')).toBe('false');

        await fireEvent.pointerUp(window, {
            pointerId: 20,
            clientX: 150,
            clientY: 150,
        });
        await tick();

        expect(header.getAttribute('aria-pressed')).toBe('true');
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');
    });

    it('cancels a stationary component gesture without emitting while preserving selection', async () => {
        type EmittedDocument = {
            components?: Array<{ id: string; x: number; y: number }>;
        };
        const emitted: EmittedDocument[] = [];
        const { container } = render(ResultSheetStudio, {
            builderDocument: sampleDocument,
            builderCatalog: mockCatalog,
            onChange: (doc: unknown) => emitted.push(doc as EmittedDocument),
        });

        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;
        const header = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        setPageRect(page);

        const startClientX = ((10 + 12) / 210) * 1000;
        const startClientY = ((10 + 12) / 297) * 1000;

        await fireEvent.pointerDown(header, {
            button: 0,
            pointerId: 30,
            clientX: startClientX,
            clientY: startClientY,
        });
        await tick();

        expect(header.getAttribute('aria-pressed')).toBe('true');
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');

        await fireEvent.pointerUp(window, {
            pointerId: 30,
            clientX: startClientX,
            clientY: startClientY,
        });
        await tick();

        expect(emitted).toHaveLength(0);
        expect(header.getAttribute('aria-pressed')).toBe('true');
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');

        await fireEvent.pointerDown(header, {
            button: 0,
            pointerId: 31,
            clientX: startClientX,
            clientY: startClientY,
        });
        await fireEvent.pointerMove(page, {
            pointerId: 31,
            buttons: 1,
            clientX: startClientX + 20,
            clientY: startClientY,
        });
        emitted.length = 0;
        await fireEvent.pointerUp(window, {
            pointerId: 31,
            clientX: startClientX + 20,
            clientY: startClientY,
        });

        expect(emitted).toHaveLength(1);
        expect(
            emitted[0].components?.find(
                (component) => component.id === 'header-1',
            )?.x,
        ).toBeGreaterThan(10);
    });

    it('updates component position when a drag is within snap tolerance', async () => {
        type EmittedDocument = {
            components?: Array<{ id: string; x: number; y: number }>;
        };
        let lastEmitted: EmittedDocument | null = null;
        const snapDocument = {
            ...sampleDocument,
            components: [
                { ...sampleDocument.components[0], x: 20, y: 10, w: 40, h: 24 },
                {
                    ...sampleDocument.components[1],
                    x: 100,
                    y: 80,
                    w: 80,
                    h: 12,
                },
            ],
        };
        const { container } = render(ResultSheetStudio, {
            builderDocument: snapDocument,
            builderCatalog: mockCatalog,
            onChange: (doc: unknown) => {
                lastEmitted = doc as EmittedDocument;
            },
        });

        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        vi.spyOn(page, 'getBoundingClientRect').mockReturnValue({
            left: 0,
            top: 0,
            width: 1000,
            height: 1000,
        } as DOMRect);

        const header = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const startClientX = ((20 + 12) / 210) * 1000;
        const startClientY = ((10 + 12) / 297) * 1000;
        const nearAlignedClientX = ((99 + 12) / 210) * 1000;

        await fireEvent.pointerDown(header, {
            button: 0,
            pointerId: 1,
            clientX: startClientX,
            clientY: startClientY,
        });
        await fireEvent.pointerMove(window, {
            pointerId: 1,
            clientX: nearAlignedClientX,
            clientY: startClientY,
        });
        await fireEvent.pointerUp(window, {
            pointerId: 1,
            clientX: nearAlignedClientX,
            clientY: startClientY,
        });

        expect(
            lastEmitted?.components?.find(
                (component) => component.id === 'header-1',
            ),
        ).toMatchObject({
            x: 100,
            y: 10,
        });
    });
    it('resizes selected component via Alt+Arrow keyboard shortcut', async () => {
        let latestDoc: { components: Array<{ id: string; w: number; h: number }> } | null = null;
        const { container } = render(ResultSheetStudio, {
            props: {
                builderDocument: {
                    schema_version: 1,
                    page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12, width_mm: 210, height_mm: 297 },
                    components: [
                        { id: 'comp-1', type: 'text', x: 10, y: 10, w: 100, h: 20, locked: false, props: { text: 'Hello' } },
                    ],
                },
                builderCatalog: { text: { label: 'Text' } },
                onChange: (doc: unknown) => {
                    latestDoc = doc as { components: Array<{ id: string; w: number; h: number }> };
                },
            },
        });

        const comp = container.querySelector('[data-result-sheet-component="comp-1"]') as HTMLElement;
        expect(comp).not.toBeNull();
        await fireEvent.pointerDown(comp, { button: 0 });
        await tick();

        const surface = container.querySelector('[data-editor-surface]') as HTMLElement;
        expect(surface).not.toBeNull();

        await fireEvent.keyDown(surface, { key: 'ArrowRight', altKey: true });
        await tick();
        expect(latestDoc?.components[0]?.w).toBe(101);

        await fireEvent.keyDown(surface, { key: 'ArrowDown', altKey: true });
        await tick();
        expect(latestDoc?.components[0]?.h).toBe(21);
    });

    it('does not render nested interactive buttons inside component buttons for resize handles', async () => {
        const { container } = render(ResultSheetStudio, {
            props: {
                builderDocument: {
                    schema_version: 1,
                    page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12, width_mm: 210, height_mm: 297 },
                    components: [
                        { id: 'comp-1', type: 'text', x: 10, y: 10, w: 100, h: 20, locked: false, props: { text: 'Hello' } },
                    ],
                },
                builderCatalog: { text: { label: 'Text' } },
            },
        });

        const comp = container.querySelector('[data-result-sheet-component="comp-1"]') as HTMLElement;
        await fireEvent.pointerDown(comp, { button: 0 });
        await tick();

        const nestedButtons = comp.querySelectorAll('button, [role="button"]');
        expect(nestedButtons.length).toBe(0);
    });
});
