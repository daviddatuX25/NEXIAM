// @vitest-environment jsdom

import { cleanup, fireEvent, render, within } from '@testing-library/svelte';
import { createRawSnippet, tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';

describe('ResultSheetStudio responsive chrome contract', () => {
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

    const builderCatalog = {
        header: {
            label: 'Header',
            description: 'Institution header',
            category: 'Structure',
        },
        field: {
            label: 'Field',
            description: 'Dynamic applicant field',
            category: 'Data',
        },
        score_table: {
            label: 'Score Table',
            description: 'Scores and subjects',
            category: 'Data',
        },
    };

    const builderDocument = {
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
        ],
    };

    function setRect(node: Element, rect: Partial<DOMRect>) {
        const base = {
            x: 0,
            y: 0,
            top: 0,
            right: 0,
            bottom: 0,
            left: 0,
            width: 0,
            height: 0,
            toJSON: () => ({}),
        };
        Object.defineProperty(node, 'getBoundingClientRect', {
            configurable: true,
            value: () => ({ ...base, ...rect }),
        });
    }
    function setClientSize(node: HTMLElement, width: number, height: number) {
        Object.defineProperty(node, 'clientWidth', {
            configurable: true,
            value: width,
        });
        Object.defineProperty(node, 'clientHeight', {
            configurable: true,
            value: height,
        });
    }

    function renderStudio(width = 390, adapterActions = null) {
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: width,
        });
        return render(ResultSheetStudio, {
            props: {
                fullBleed: true,
                builderDocument,
                builderCatalog,
                adapterActions,
            },
        });
    }

    it('preserves page context when an array-shaped builder document arrives', () => {
        const { container } = render(ResultSheetStudio, {
            props: {
                fullBleed: true,
                builderDocument: [],
                builderDefaults: builderDocument,
                builderCatalog,
            },
        });

        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;

        expect(page).toBeTruthy();
        expect(page.getAttribute('style')).toContain('width: 210mm');
        expect(
            container.querySelector('[data-result-sheet-component]'),
        ).toBeNull();
    });

    function createAdapterActionSnippet() {
        return createRawSnippet(() => ({
            render: () =>
                '<div data-result-sheet-test-adapter-actions><button type="button" data-result-sheet-test-source-action>Adapter source action</button></div>',
            setup: () => {},
        }));
    }

    it.each([768, 390])(
        'uses one workspace-anchored bottom surface below 1024px at %dpx',
        async (width) => {
            const { container } = renderStudio(width);
            const studio = container.querySelector(
                '[data-result-sheet-studio]',
            ) as HTMLElement;
            const workspace = container.querySelector(
                '[data-result-sheet-workspace]',
            ) as HTMLElement;
            const canvas = container.querySelector(
                '[aria-label="Visual document canvas"]',
            ) as HTMLElement;
            const pageFrame = container.querySelector(
                '[data-result-sheet-page-frame]',
            ) as HTMLElement;
            const surface = container.querySelector(
                '[data-result-sheet-mobile-surface]',
            ) as HTMLElement;
            const palette = container.querySelector(
                '#result-sheet-component-palette',
            ) as HTMLElement;
            const dock = container.querySelector(
                '[data-result-sheet-command-dock]',
            ) as HTMLElement;
            const add = within(dock).getByRole('button', { name: 'Add' });
            const layers = within(dock).getByRole('button', { name: 'Layers' });
            const canvasClass = canvas.className;

            expect(studio.className).toContain('min-h-0');
            expect(studio.className).toContain('flex-1');
            expect(studio.className).toContain('basis-0');
            expect(studio.className).not.toContain('h-full');
            expect(workspace.className).toContain('min-h-0');
            expect(workspace.className).toContain('flex-1');
            expect(workspace.className).toContain('basis-0');
            expect(workspace.className).not.toContain('h-full');
            expect(canvas.className).toContain('min-h-0');
            expect(canvas.className).toContain('flex-1');
            expect(canvas.className).toContain('basis-0');
            expect(canvas.className).not.toContain('h-full');
            expect(canvas.parentElement).toBe(workspace);
            expect(surface.contains(canvas)).toBe(false);
            const floatingToolbar = container.querySelector(
                '[data-result-sheet-floating-toolbar]',
            ) as HTMLElement;
            expect(floatingToolbar).toBeTruthy();
            expect(floatingToolbar.className).toContain('absolute');
            expect(floatingToolbar.className).toContain('flex-nowrap');
            expect(floatingToolbar.className).not.toContain('flex-wrap');
            expect(
                within(floatingToolbar).getByRole('button', {
                    name: 'Select tool',
                }),
            ).toBeTruthy();
            expect(
                within(floatingToolbar).getByRole('button', {
                    name: 'Pan tool',
                }),
            ).toBeTruthy();
            expect(
                within(floatingToolbar).getByRole('button', { name: 'Undo' }),
            ).toBeTruthy();
            expect(
                within(floatingToolbar).getByRole('button', { name: 'Redo' }),
            ).toBeTruthy();
            expect(Number.parseFloat(pageFrame.style.width)).toBeGreaterThan(0);
            expect(Number.parseFloat(pageFrame.style.height)).toBeGreaterThan(
                0,
            );
            expect(dock).toBeTruthy();
            expect(dock.parentElement).toBe(studio);
            expect(dock.previousElementSibling).toBe(workspace);
            expect(dock.className).toContain('w-full');
            expect(dock.className).toContain('shrink-0');
            expect(surface.dataset.resultSheetMobileSurfaceState).toBe(
                'closed',
            );
            expect(palette.classList.contains('hidden')).toBe(true);
            expect(workspace.className).toContain('overflow-hidden');
            expect(workspace.className).not.toContain('overflow-y-auto');
            expect(
                container.querySelector('[data-result-sheet-panel-backdrop]'),
            ).toBeNull();

            await fireEvent.click(add);
            await tick();
            expect(surface.dataset.resultSheetMobileSurfaceState).toBe('open');
            expect(surface.dataset.resultSheetMobileSurfacePanel).toBe('add');
            expect(surface.className).toContain('absolute');
            expect(surface.className).toContain('bottom-3');
            expect(surface.className).toContain('z-30');
            expect(palette.dataset.resultSheetPanelPosition).toBe(
                'bottom-surface',
            );
            expect(palette.classList.contains('hidden')).toBe(false);
            expect(
                container
                    .querySelector('#result-sheet-inspector')
                    ?.classList.contains('hidden'),
            ).toBe(true);
            expect(canvas.className).toBe(canvasClass);
            expect(canvas.parentElement).toBe(workspace);
            expect(surface.contains(canvas)).toBe(false);

            const search = within(palette).getByRole('searchbox', {
                name: 'Search component types',
            });
            await fireEvent.input(search, { target: { value: 'score' } });
            await tick();
            expect(palette.textContent).toContain('1 of 3 types');
            expect(
                within(palette).getByRole('button', { name: 'Score Table' }),
            ).toBeTruthy();
            expect(
                within(palette).queryByRole('button', { name: 'Header' }),
            ).toBeNull();

            await fireEvent.click(layers);
            await tick();
            const inspector = container.querySelector(
                '#result-sheet-inspector',
            ) as HTMLElement;
            expect(surface.dataset.resultSheetMobileSurfacePanel).toBe(
                'layers',
            );
            expect(inspector.dataset.resultSheetPanelPosition).toBe(
                'bottom-surface',
            );
            expect(inspector.classList.contains('hidden')).toBe(false);
            expect(palette.classList.contains('hidden')).toBe(true);
            expect(canvas.className).toBe(canvasClass);

            await fireEvent.click(add);
            await tick();
            expect(surface.dataset.resultSheetMobileSurfacePanel).toBe('add');
            expect(palette.classList.contains('hidden')).toBe(false);
            expect(inspector.classList.contains('hidden')).toBe(true);

            await fireEvent.click(add);
            await tick();
            expect(surface.dataset.resultSheetMobileSurfaceState).toBe(
                'closed',
            );
            expect(document.activeElement).toBe(add);
        },
    );
    it('keeps the desktop Add rail/shelf in the grid and reports truthful palette counts', async () => {
        const { container } = renderStudio(1440);
        const workspace = container.querySelector(
            '[data-result-sheet-workspace]',
        ) as HTMLElement;
        const palette = container.querySelector(
            '#result-sheet-component-palette',
        ) as HTMLElement;
        const paletteHeader = container.querySelector(
            '[data-result-sheet-palette-header]',
        ) as HTMLElement;
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;

        expect(
            container.querySelector('[data-result-sheet-command-dock]'),
        ).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-mobile-surface]')
                ?.dataset.resultSheetMobileSurfaceState,
        ).toBe('closed');
        expect(palette.dataset.resultSheetPanelPosition).toBe('left-rail');
        expect(palette.dataset.resultSheetPanelSurface).toBe('rail');
        expect(workspace.className).toContain('grid-cols-[88px_minmax(0,1fr)]');
        expect(workspace.className).toContain('basis-0');
        expect(paletteHeader.className).toContain('justify-center');
        expect(paletteHeader.className).not.toContain('justify-between');
        expect(paletteHeader.querySelector('h3')).toBeNull();
        const collapsedAdd = within(palette).getByRole('button', {
            name: 'Add component',
        });
        expect(collapsedAdd.querySelector('svg')).toBeTruthy();
        expect(collapsedAdd.querySelector('.sr-only')).toBeTruthy();
        expect(collapsedAdd.getAttribute('title')).toBe('Add component');
        expect(palette.textContent).not.toContain('available types');
        expect(canvas.className).toContain('lg:w-auto');
        const surface = container.querySelector(
            '[data-result-sheet-mobile-surface]',
        );
        expect(canvas.parentElement).toBe(workspace);
        expect(surface?.contains(canvas)).toBe(false);

        await fireEvent.click(collapsedAdd);
        await tick();
        expect(palette.dataset.resultSheetPanelSurface).toBe('expanded');
        expect(paletteHeader.className).toContain('justify-between');
        expect(paletteHeader.className).not.toContain('justify-center');
        expect(paletteHeader.querySelector('h3')?.textContent).toContain(
            'Add components',
        );
        expect(palette.textContent).toContain('3 available types');
        expect(
            within(palette).getByRole('searchbox', {
                name: 'Search component types',
            }),
        ).toBeTruthy();

        const search = within(palette).getByRole('searchbox', {
            name: 'Search component types',
        });
        await fireEvent.input(search, { target: { value: 'field' } });
        await tick();
        expect(palette.textContent).toContain('1 of 3 types');
    });

    it('enters mobile component focus with a half Inspector sheet and restores the viewport on Escape', async () => {
        const { container } = renderStudio(390);
        const studio = container.querySelector(
            '[data-result-sheet-studio]',
        ) as HTMLElement;
        const header = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLButtonElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;
        const toolbar = container.querySelector(
            '[data-result-sheet-floating-toolbar]',
        ) as HTMLElement;
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const pageFrame = container.querySelector(
            '[data-result-sheet-page-frame]',
        ) as HTMLElement;
        const initialDock = container.querySelector(
            '[data-result-sheet-command-dock]',
        ) as HTMLElement;

        await fireEvent.click(
            within(toolbar).getByRole('button', { name: 'Zoom out' }),
        );
        await tick();
        const beforeViewport = {
            zoomMode: studio.dataset.resultSheetZoomMode,
            zoom: studio.dataset.resultSheetZoom,
            panX: studio.dataset.resultSheetPanX,
            panY: studio.dataset.resultSheetPanY,
        };

        await fireEvent.pointerDown(header, {
            button: 0,
            pointerId: 1,
            clientX: 20,
            clientY: 20,
        });
        await tick();
        await tick();

        expect(header.getAttribute('aria-pressed')).toBe('true');
        expect(studio.dataset.resultSheetMobileFocus).toBe('true');
        expect(
            container.querySelector(
                '[data-result-sheet-mobile-context-header]',
            ),
        ).toBeNull();
        expect(
            within(inspector).getByRole('button', {
                name: 'Close inspector panel',
            }),
        ).toBeTruthy();
        expect(
            container.querySelector('[data-result-sheet-floating-toolbar]'),
        ).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-command-dock]'),
        ).toBeNull();
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');
        expect(inspector.dataset.resultSheetPanelPosition).toBe('bottom-sheet');
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('half');
        expect(inspector.getAttribute('role')).toBe('complementary');
        expect(inspector.getAttribute('aria-modal')).toBeNull();
        expect(
            within(inspector).getByRole('button', {
                name: 'Peek inspector sheet',
            }),
        ).toBeTruthy();
        expect(
            within(inspector).getByRole('button', {
                name: 'Half inspector sheet',
            }),
        ).toBeTruthy();
        expect(
            within(inspector).getByRole('button', {
                name: 'Full inspector sheet',
            }),
        ).toBeTruthy();
        expect(Number.parseFloat(pageFrame.style.width)).toBeGreaterThan(0);
        expect(Number.parseFloat(pageFrame.style.height)).toBeGreaterThan(0);
        expect(
            Number(canvas.dataset.resultSheetSafeBottom ?? '0'),
        ).toBeGreaterThanOrEqual(0);

        await fireEvent.click(
            within(inspector).getByRole('button', {
                name: 'Full inspector sheet',
            }),
        );
        await tick();
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('full');
        await fireEvent.click(
            within(inspector).getByRole('button', {
                name: 'Peek inspector sheet',
            }),
        );
        await tick();
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('peek');

        await fireEvent.keyDown(window, { key: 'Escape', code: 'Escape' });
        await tick();
        await tick();
        expect(studio.dataset.resultSheetMobileFocus).toBe('false');
        expect(
            container.querySelector(
                '[data-result-sheet-mobile-context-header]',
            ),
        ).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-floating-toolbar]'),
        ).toBeTruthy();
        expect(
            container.querySelector('[data-result-sheet-command-dock]'),
        ).toBeTruthy();
        expect(inspector.dataset.resultSheetPanelState).toBe('closed');
        expect(studio.dataset.resultSheetZoomMode).toBe(
            beforeViewport.zoomMode,
        );
        expect(studio.dataset.resultSheetZoom).toBe(beforeViewport.zoom);
        expect(studio.dataset.resultSheetPanX).toBe(beforeViewport.panX);
        expect(studio.dataset.resultSheetPanY).toBe(beforeViewport.panY);
        expect(header.getAttribute('aria-pressed')).toBe('true');
        expect(document.activeElement).toBe(header);
        expect(initialDock).toBeTruthy();
        expect(
            container.querySelector('[data-result-sheet-command-dock]'),
        ).toBeTruthy();
    });

    it('keeps adapter actions outside generic Studio source ownership', async () => {
        const { container } = renderStudio(1440, createAdapterActionSnippet());
        const toolbar = container.querySelector(
            '[data-result-sheet-toolbar]',
        ) as HTMLElement;
        const actions = container.querySelector(
            '[data-result-sheet-adapter-actions]',
        ) as HTMLElement;

        expect(actions).toBeTruthy();
        expect(
            within(toolbar).getByRole('button', {
                name: 'Adapter source action',
            }),
        ).toBeTruthy();
        expect(container.textContent).not.toContain('Builder JSON');
        expect(container.textContent).not.toContain('Suite Prompt');
    });

    it('fits after canvas measurement and mobile surface transitions without canvas reflow', async () => {
        const { container } = renderStudio(390);
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const surface = container.querySelector(
            '[data-result-sheet-mobile-surface]',
        ) as HTMLElement;
        const dock = container.querySelector(
            '[data-result-sheet-command-dock]',
        ) as HTMLElement;
        const add = within(dock).getByRole('button', { name: 'Add' });
        const canvasClass = canvas.className;

        setClientSize(canvas, 360, 560);
        setRect(canvas, {
            top: 100,
            bottom: 660,
            left: 0,
            right: 360,
            width: 360,
            height: 560,
        });
        const initialInvalidation = Number(
            canvas.dataset.resultSheetFitInvalidation ?? 0,
        );
        window.dispatchEvent(new Event('resize'));
        await tick();
        await tick();

        const measuredInvalidation = Number(
            canvas.dataset.resultSheetFitInvalidation ?? 0,
        );
        expect(measuredInvalidation).toBeGreaterThan(initialInvalidation);
        expect(canvas.className).toBe(canvasClass);

        await fireEvent.click(add);
        await tick();
        setRect(surface, {
            top: 430,
            bottom: 610,
            left: 12,
            right: 378,
            width: 366,
            height: 180,
        });
        window.dispatchEvent(new Event('resize'));
        await tick();
        await tick();
        const openInvalidation = Number(
            canvas.dataset.resultSheetFitInvalidation ?? 0,
        );
        expect(openInvalidation).toBeGreaterThan(measuredInvalidation);
        expect(canvas.className).toBe(canvasClass);

        await fireEvent.click(add);
        await tick();
        await tick();
        const closedInvalidation = Number(
            canvas.dataset.resultSheetFitInvalidation ?? 0,
        );
        expect(closedInvalidation).toBeGreaterThan(openInvalidation);
        expect(canvas.className).toBe(canvasClass);
    });

    it('reserves the measured Xiam safe inset for Fit calculations', async () => {
        const { container } = renderStudio(390);
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        const dock = document.createElement('div');
        dock.className = 'xiam-dock';
        document.body.append(dock);
        setRect(canvas, {
            top: 100,
            bottom: 700,
            left: 0,
            right: 390,
            width: 390,
            height: 600,
        });
        setRect(page, {
            top: 110,
            bottom: 650,
            left: 20,
            right: 340,
            width: 320,
            height: 540,
        });
        setRect(dock, {
            top: 620,
            bottom: 700,
            left: 250,
            right: 390,
            width: 140,
            height: 80,
        });
        window.dispatchEvent(new Event('resize'));
        await tick();

        expect(Number(canvas.dataset.resultSheetSafeBottom)).toBeGreaterThan(0);
        dock.remove();
    });
});
