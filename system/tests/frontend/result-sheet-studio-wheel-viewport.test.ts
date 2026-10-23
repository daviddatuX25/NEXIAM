// @vitest-environment jsdom

import { cleanup, fireEvent, render, within } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { normalizeWheelDelta } from '../../resources/js/lib/studio/viewport.js';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';

describe('ResultSheetStudio Wheel Zoom & Tablet/Mobile Viewport Contract', () => {
    const initialViewportWidth = window.innerWidth;

    function setViewportWidth(width: number) {
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: width,
        });
    }

    beforeEach(() => {
        setViewportWidth(1024);
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
        setViewportWidth(initialViewportWidth);
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

    function installRenderedGeometry(
        container: HTMLElement,
        width = 960,
        height = 720,
    ) {
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        const frame = container.querySelector(
            '[data-result-sheet-page-frame]',
        ) as HTMLElement;
        const page = container.querySelector(
            '[data-result-sheet-page]',
        ) as HTMLElement;
        const component = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const canvasRect = { left: 40, top: 24, width, height };
        setClientSize(canvas, width, height);
        setRect(canvas, {
            ...canvasRect,
            right: canvasRect.left + width,
            bottom: canvasRect.top + height,
        });
        const computed = window.getComputedStyle(canvas);
        const px = (value: string) => Number.parseFloat(value) || 0;
        const paddingLeft = px(computed.paddingLeft);
        const paddingRight = px(computed.paddingRight);
        const paddingTop = px(computed.paddingTop);
        const borderLeft =
            Number(canvas.clientLeft) || px(computed.borderLeftWidth);
        const borderTop =
            Number(canvas.clientTop) || px(computed.borderTopWidth);
        const contentLeft = canvasRect.left + borderLeft + paddingLeft;
        const contentTop = canvasRect.top + borderTop + paddingTop;
        const contentWidth = width - borderLeft - paddingLeft - paddingRight;

        Object.defineProperty(frame, 'getBoundingClientRect', {
            configurable: true,
            value: () => {
                const frameWidth = Number.parseFloat(frame.style.width) || 0;
                const frameHeight = Number.parseFloat(frame.style.height) || 0;
                const left =
                    frameWidth <= contentWidth
                        ? contentLeft + (contentWidth - frameWidth) / 2
                        : contentLeft;
                const top = contentTop;
                return {
                    x: left,
                    y: top,
                    top,
                    left,
                    right: left + frameWidth,
                    bottom: top + frameHeight,
                    width: frameWidth,
                    height: frameHeight,
                    toJSON: () => ({}),
                };
            },
        });

        Object.defineProperty(page, 'getBoundingClientRect', {
            configurable: true,
            value: () => {
                const frameRect = frame.getBoundingClientRect();
                const transform = page.parentElement?.style.transform ?? '';
                const match = transform.match(
                    /translate\(([-\d.]+)px,([-\d.]+)px\) scale\(([-\d.]+)\)/,
                );
                const panX = Number(match?.[1] ?? 0);
                const panY = Number(match?.[2] ?? 0);
                const scale = Number(match?.[3] ?? 1);
                const pageWidth = 210 * (96 / 25.4) * scale;
                const pageHeight = 297 * (96 / 25.4) * scale;
                const left = frameRect.left + panX;
                const top = frameRect.top + panY;
                return {
                    x: left,
                    y: top,
                    top,
                    left,
                    right: left + pageWidth,
                    bottom: top + pageHeight,
                    width: pageWidth,
                    height: pageHeight,
                    toJSON: () => ({}),
                };
            },
        });

        return {
            canvas,
            frame,
            page,
            component,
            canvasRect,
            contentCenterX: contentLeft + contentWidth / 2,
        };
    }

    async function settleViewport() {
        window.dispatchEvent(new Event('resize'));
        await tick();
        await tick();
    }

    it.each([
        { width: 960, height: 720 },
        { width: 640, height: 860 },
    ])(
        'keeps the rendered paper point fixed across canvas, paper, background, and nested-component wheel targets ($width x $height)',
        async ({ width, height }) => {
            const { container } = render(ResultSheetStudio, {
                props: {
                    builderDocument: sampleDocument,
                    builderCatalog: mockCatalog,
                },
            });
            const rendered = installRenderedGeometry(container, width, height);
            await settleViewport();

            const paperWidthPx = 210 * (96 / 25.4);
            const targets = [
                {
                    name: 'canvas center',
                    target: rendered.canvas,
                    point: () => ({
                        x:
                            rendered.canvasRect.left +
                            rendered.canvasRect.width / 2,
                        y:
                            rendered.canvasRect.top +
                            rendered.canvasRect.height / 2,
                    }),
                },
                {
                    name: 'paper corner',
                    target: rendered.canvas,
                    point: () => {
                        const rect = rendered.page.getBoundingClientRect();
                        return { x: rect.left + 4, y: rect.top + 4 };
                    },
                },
                {
                    name: 'canvas background',
                    target: rendered.canvas,
                    point: () => ({
                        x: rendered.canvasRect.left + 8,
                        y: rendered.canvasRect.top + 8,
                    }),
                },
                {
                    name: 'nested component',
                    target: rendered.component,
                    point: () => {
                        const rect = rendered.page.getBoundingClientRect();
                        return {
                            x: rect.left + rect.width * 0.35,
                            y: rect.top + rect.height * 0.15,
                        };
                    },
                },
            ];

            for (const { name, target, point } of targets) {
                const cursor = point();
                const before = rendered.page.getBoundingClientRect();
                const beforeScale = before.width / paperWidthPx;
                const sheetX = (cursor.x - before.left) / beforeScale;
                const sheetY = (cursor.y - before.top) / beforeScale;
                const event = new WheelEvent('wheel', {
                    bubbles: true,
                    cancelable: true,
                    deltaY: -40,
                    deltaMode: 0,
                    clientX: cursor.x,
                    clientY: cursor.y,
                });

                expect(
                    target.dispatchEvent(event),
                    `${name} should prevent page scroll`,
                ).toBe(false);
                await tick();
                await tick();

                const after = rendered.page.getBoundingClientRect();
                const afterScale = after.width / paperWidthPx;
                const drift = Math.hypot(
                    after.left + sheetX * afterScale - cursor.x,
                    after.top + sheetY * afterScale - cursor.y,
                );
                expect(afterScale, `${name} should zoom in`).toBeGreaterThan(
                    beforeScale,
                );
                expect(drift, `${name} anchor drift`).toBeLessThan(1);
            }
        },
    );

    it('normalizes line-mode wheel deltas while preserving fine trackpad movement', async () => {
        expect(normalizeWheelDelta(3, 1)).toBe(48);
        expect(normalizeWheelDelta(1, 2, 600)).toBe(600);

        const { container } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });
        const rendered = installRenderedGeometry(container);
        await settleViewport();

        const paperWidthPx = 210 * (96 / 25.4);
        const before = rendered.page.getBoundingClientRect();
        const beforeScale = before.width / paperWidthPx;
        const lineEvent = new WheelEvent('wheel', {
            bubbles: true,
            cancelable: true,
            deltaY: -3,
            deltaMode: 1,
            clientX: rendered.canvasRect.left + rendered.canvasRect.width / 2,
            clientY: rendered.canvasRect.top + rendered.canvasRect.height / 2,
        });
        expect(rendered.canvas.dispatchEvent(lineEvent)).toBe(false);
        await tick();
        await tick();

        const afterLine = rendered.page.getBoundingClientRect();
        const lineScale = afterLine.width / paperWidthPx;
        expect(lineScale).toBeGreaterThan(beforeScale);
        expect(lineScale / beforeScale).toBeLessThan(1.1);

        const beforeTrackpad = rendered.page.getBoundingClientRect();
        const trackpadEvent = new WheelEvent('wheel', {
            bubbles: true,
            cancelable: true,
            deltaY: -0.5,
            deltaMode: 0,
            clientX: rendered.canvasRect.left + rendered.canvasRect.width / 2,
            clientY: rendered.canvasRect.top + rendered.canvasRect.height / 2,
        });
        expect(rendered.canvas.dispatchEvent(trackpadEvent)).toBe(false);
        await tick();
        await tick();

        const afterTrackpad = rendered.page.getBoundingClientRect();
        expect(afterTrackpad.width).toBeGreaterThan(beforeTrackpad.width);
        expect(afterTrackpad.width / beforeTrackpad.width).toBeLessThan(
            lineScale / beforeScale,
        );
    });

    it('resets pan and recomputes the centered frame when Fit is selected', async () => {
        const { container, getByRole } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });
        const rendered = installRenderedGeometry(container, 900, 700);
        await settleViewport();

        const before = rendered.page.getBoundingClientRect();
        const zoomEvent = new WheelEvent('wheel', {
            bubbles: true,
            cancelable: true,
            deltaY: -100,
            clientX: before.left + 12,
            clientY: before.top + 12,
        });
        rendered.canvas.dispatchEvent(zoomEvent);
        await tick();
        await tick();
        expect(rendered.page.parentElement?.style.transform).not.toContain(
            'translate(0px,0px)',
        );

        await fireEvent.click(getByRole('button', { name: 'Fit page' }));
        await tick();
        await tick();

        expect(rendered.page.parentElement?.style.transform).toContain(
            'translate(0px,0px)',
        );
        const frame = rendered.frame.getBoundingClientRect();
        expect(frame.left + frame.width / 2).toBeCloseTo(
            rendered.contentCenterX,
            1,
        );
    });

    it('keeps repeated toolbar zoom steps manual after the initial fit', async () => {
        const { container, getByRole } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });
        const rendered = installRenderedGeometry(container, 900, 700);
        await settleViewport();

        const scaleFromTransform = () => {
            const transform =
                rendered.page.parentElement?.style.transform ?? '';
            return Number(transform.match(/scale\(([-\d.]+)\)/)?.[1] ?? 0);
        };
        const fittedScale = scaleFromTransform();

        const zoomIn = getByRole('button', { name: 'Zoom in' });
        for (let index = 0; index < 40; index += 1) {
            await fireEvent.click(zoomIn);
            await tick();
            await tick();
        }

        const maxedScale = scaleFromTransform();
        expect(maxedScale).toBeCloseTo(3, 5);
        expect(maxedScale).toBeGreaterThan(fittedScale);

        await fireEvent.click(getByRole('button', { name: 'Zoom out' }));
        await tick();
        await tick();
        expect(scaleFromTransform()).toBeLessThan(maxedScale);

        await fireEvent.click(getByRole('button', { name: 'Fit page' }));
        await tick();
        await tick();
        expect(scaleFromTransform()).toBeCloseTo(fittedScale, 5);
    });

    it('triggers cursor-anchored zoom on wheel with Ctrl/Cmd or trackpad pinch', async () => {
        const { getByRole, getByText } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });

        const canvas = getByRole('region', { name: 'Visual document canvas' });
        expect(canvas).toBeTruthy();

        const initialZoom = Number.parseInt(
            getByText(/%/).textContent?.trim() ?? '0',
            10,
        );
        expect(initialZoom).toBeGreaterThan(0);

        // Dispatch Ctrl+Wheel event (scrolling up to zoom in)
        const wheelEvent = new WheelEvent('wheel', {
            bubbles: true,
            cancelable: true,
            ctrlKey: true,
            deltaY: -100,
            clientX: 400,
            clientY: 300,
        });
        const defaultPrevented = !canvas.dispatchEvent(wheelEvent);

        // Event default must be prevented to stop browser page scroll.
        expect(defaultPrevented).toBe(true);
        await tick();
        await tick();

        const nextZoom = Number.parseInt(
            getByText(/%/).textContent?.trim() ?? '0',
            10,
        );
        expect(nextZoom).toBeGreaterThan(initialZoom);
    });

    it('triggers cursor-anchored zoom directly when Pan/Hand tool is active', async () => {
        const { getByRole, getByTitle, getByText } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });

        // Switch to Pan tool (H)
        const panToolButton = getByTitle('Pan / Hand tool (H or Space+Drag)');
        await fireEvent.click(panToolButton);

        const canvas = getByRole('region', { name: 'Visual document canvas' });

        // Wheel event without Ctrl/Cmd when Pan tool is active -> zooms
        const wheelEvent = new WheelEvent('wheel', {
            bubbles: true,
            cancelable: true,
            deltaY: -100,
            clientX: 350,
            clientY: 250,
        });
        const beforeZoom = Number.parseInt(
            getByText(/%/).textContent?.trim() ?? '0',
            10,
        );
        const prevented = !canvas.dispatchEvent(wheelEvent);
        expect(prevented).toBe(true);
        await tick();
        await tick();
        const afterZoom = Number.parseInt(
            getByText(/%/).textContent?.trim() ?? '0',
            10,
        );
        expect(afterZoom).toBeGreaterThan(beforeZoom);
    });

    it('zooms out and prevents default browser scroll on standard wheel in select mode', async () => {
        const { getByRole, getByText } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });

        const canvas = getByRole('region', { name: 'Visual document canvas' });

        const beforeZoom = Number.parseInt(
            getByText(/%/).textContent?.trim() ?? '0',
            10,
        );
        const wheelEvent = new WheelEvent('wheel', {
            bubbles: true,
            cancelable: true,
            deltaX: 20,
            deltaY: 40,
            clientX: 300,
            clientY: 200,
        });
        const prevented = !canvas.dispatchEvent(wheelEvent);
        expect(prevented).toBe(true);
        await tick();
        await tick();
        const afterZoom = Number.parseInt(
            getByText(/%/).textContent?.trim() ?? '0',
            10,
        );
        expect(afterZoom).toBeLessThan(beforeZoom);
    });

    it('clamps zoom scale between minimum 0.25 (25%) and maximum 3.0 (300%)', async () => {
        const { getByRole, getByText } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
            },
        });

        const canvas = getByRole('region', { name: 'Visual document canvas' });

        // Zoom in repeatedly with Ctrl+Wheel
        for (let i = 0; i < 40; i++) {
            canvas.dispatchEvent(
                new WheelEvent('wheel', {
                    bubbles: true,
                    cancelable: true,
                    ctrlKey: true,
                    deltaY: -200,
                    clientX: 400,
                    clientY: 300,
                }),
            );
            await tick();
            await tick();
        }

        const maxZoomText = getByText(/%/).textContent?.trim();
        const maxZoomNumber = parseInt(maxZoomText || '0', 10);
        expect(maxZoomNumber).toBeLessThanOrEqual(300);

        // Zoom out repeatedly with Ctrl+Wheel
        for (let i = 0; i < 60; i++) {
            canvas.dispatchEvent(
                new WheelEvent('wheel', {
                    bubbles: true,
                    cancelable: true,
                    ctrlKey: true,
                    deltaY: 200,
                    clientX: 400,
                    clientY: 300,
                }),
            );
            await tick();
            await tick();
        }

        const minZoomText = getByText(/%/).textContent?.trim();
        const minZoomNumber = parseInt(minZoomText || '0', 10);
        expect(minZoomNumber).toBeGreaterThanOrEqual(25);
    });

    it('opens the contextual Inspector as a non-modal half-height sheet on mobile/tablet', async () => {
        setViewportWidth(390);
        const { container } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
                fullBleed: true,
            },
        });

        const studio = container.querySelector(
            '[data-result-sheet-studio]',
        ) as HTMLElement;
        const component = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;

        expect(inspector.dataset.resultSheetPanelState).toBe('closed');
        expect(inspector.getAttribute('role')).toBe('complementary');
        expect(inspector.getAttribute('aria-modal')).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-panel-backdrop]'),
        ).toBeNull();

        await fireEvent.pointerDown(component, { button: 0 });
        await tick();
        await tick();
        expect(component.getAttribute('aria-pressed')).toBe('true');
        expect(studio.dataset.resultSheetMobileFocus).toBe('true');
        expect(inspector.dataset.resultSheetPanelState).toBe('expanded');
        expect(inspector.dataset.resultSheetPanelPosition).toBe('bottom-sheet');
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('half');
        expect(
            container.querySelector(
                '[data-result-sheet-mobile-context-header]',
            ),
        ).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-floating-toolbar]'),
        ).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-command-dock]'),
        ).toBeNull();
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
        expect(inspector.getAttribute('aria-modal')).toBeNull();

        await fireEvent.click(
            within(inspector).getByRole('button', {
                name: 'Close inspector panel',
            }),
        );
        await tick();
        expect(studio.dataset.resultSheetMobileFocus).toBe('false');
        expect(inspector.dataset.resultSheetPanelState).toBe('closed');
        expect(component.getAttribute('aria-pressed')).toBe('true');
    });

    it('exposes accessible snap controls and keeps mobile Inspector content internally scrollable', async () => {
        setViewportWidth(390);
        const { container } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
                fullBleed: true,
            },
        });

        const component = container.querySelector(
            '[data-result-sheet-component="field-1"]',
        ) as HTMLElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;
        const surface = container.querySelector(
            '[data-result-sheet-mobile-surface]',
        ) as HTMLElement;
        await fireEvent.pointerDown(component, { button: 0 });
        await tick();
        await tick();

        expect(inspector.dataset.resultSheetInspectorSnap).toBe('half');
        expect(surface.className).toContain('h-[min(52dvh,32rem)]');
        expect(inspector.className).toContain('overflow-y-auto');
        expect(inspector.className).toContain('overscroll-contain');
        expect(
            inspector.querySelector(
                '[data-result-sheet-inspector-snap-controls]',
            ),
        ).toBeTruthy();
        expect(
            inspector.querySelector(
                '[data-result-sheet-inspector-drag-handle]',
            ),
        ).toBeNull();
        expect(inspector.querySelector('[aria-modal="true"]')).toBeNull();
        expect(
            container.querySelector('[data-result-sheet-panel-backdrop]'),
        ).toBeNull();

        await fireEvent.click(
            within(inspector).getByRole('button', {
                name: 'Full inspector sheet',
            }),
        );
        await tick();
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('full');
        expect(surface.className).toContain(
            'h-[calc(100dvh-env(safe-area-inset-bottom))]',
        );
        await fireEvent.click(
            within(inspector).getByRole('button', {
                name: 'Peek inspector sheet',
            }),
        );
        await tick();
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('peek');
        expect(surface.className).toContain('h-[min(24dvh,13rem)]');
    });

    it('keeps the contextual sheet nonzero and scrollable below desktop', async () => {
        setViewportWidth(390);
        const { container } = render(ResultSheetStudio, {
            props: {
                builderDocument: sampleDocument,
                builderCatalog: mockCatalog,
                fullBleed: true,
            },
        });

        const component = container.querySelector(
            '[data-result-sheet-component="header-1"]',
        ) as HTMLElement;
        const inspector = container.querySelector(
            '#result-sheet-inspector',
        ) as HTMLElement;
        const surface = container.querySelector(
            '[data-result-sheet-mobile-surface]',
        ) as HTMLElement;
        const canvas = container.querySelector(
            '[aria-label="Visual document canvas"]',
        ) as HTMLElement;
        await fireEvent.pointerDown(component, { button: 0 });
        await tick();
        await tick();

        expect(inspector.dataset.resultSheetPanelPosition).toBe('bottom-sheet');
        expect(inspector.dataset.resultSheetInspectorSnap).toBe('half');
        expect(inspector.className).toContain('overflow-y-auto');
        expect(inspector.className).toContain('overscroll-contain');
        expect(surface.className).toContain('h-[min(52dvh,32rem)]');
        expect(canvas.className).toContain('min-h-0');
        expect(canvas.className).toContain('flex-1');
    });
});
