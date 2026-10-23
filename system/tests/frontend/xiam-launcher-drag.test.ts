// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    calculateLauncherCoordinates,
    createLauncherDragTracker,
    getPersistedPosition,
    persistPosition,
    snapToSide,
} from '../../resources/js/lib/xiam-launcher-drag';

describe('Xiam launcher drag helpers', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.stubGlobal('innerWidth', 1000);
        vi.stubGlobal('innerHeight', 800);
        vi.stubGlobal('getComputedStyle', () => ({
            paddingTop: '0px',
            paddingRight: '0px',
            paddingBottom: '0px',
            paddingLeft: '0px',
            display: 'block',
            visibility: 'visible',
        }));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it.each([
        ['left', 0, { left: 20, top: 80 }],
        ['left', 0.5, { left: 20, top: 406 }],
        ['left', 1, { left: 20, top: 732 }],
        ['right', 0, { left: 932, top: 80 }],
        ['right', 0.5, { left: 932, top: 406 }],
        ['right', 1, { left: 932, top: 732 }],
    ] as const)(
        'calculates %s-side coordinates at vertical ratio %s',
        (side, yRatio, expected) => {
            expect(calculateLauncherCoordinates({ side, yRatio })).toEqual(expected);
        },
    );

    it.each([
        ['bottom-right', { side: 'right', yRatio: 1 }],
        ['bottom-left', { side: 'left', yRatio: 1 }],
        ['top-right', { side: 'right', yRatio: 0 }],
        ['top-left', { side: 'left', yRatio: 0 }],
    ] as const)('reads legacy corner %s', (corner, expected) => {
        localStorage.setItem('nexiam.xiam.launcher-corner', corner);
        expect(getPersistedPosition()).toEqual(expected);
    });

    it('reads the default and persists a side with its vertical ratio', () => {
        expect(getPersistedPosition()).toEqual({ side: 'right', yRatio: 1 });

        persistPosition({ side: 'left', yRatio: 0.42 });

        expect(localStorage.getItem('nexiam.xiam.launcher-corner')).toBe(
            JSON.stringify({ side: 'left', yRatio: 0.42 }),
        );
        expect(getPersistedPosition()).toEqual({ side: 'left', yRatio: 0.42 });
    });

    it('clamps persisted ratios to the supported range', () => {
        localStorage.setItem(
            'nexiam.xiam.launcher-corner',
            JSON.stringify({ side: 'left', yRatio: 2 }),
        );

        expect(getPersistedPosition()).toEqual({ side: 'left', yRatio: 1 });
    });

    it.each([
        [200, 80, { side: 'left', yRatio: 0 }, { left: 20, top: 80 }],
        [200, 406, { side: 'left', yRatio: 0.5 }, { left: 20, top: 406 }],
        [800, 732, { side: 'right', yRatio: 1 }, { left: 932, top: 732 }],
        [800, 406, { side: 'right', yRatio: 0.5 }, { left: 932, top: 406 }],
    ] as const)(
        'snaps x=%i to the nearest side while preserving y=%i',
        (x, y, position, coordinates) => {
            expect(snapToSide(x, y)).toEqual({ position, coordinates });
        },
    );

    it('clamps snap height to the safe vertical range', () => {
        expect(snapToSide(900, -20)).toEqual({
            position: { side: 'right', yRatio: 0 },
            coordinates: { left: 932, top: 80 },
        });
    });

    it('treats movement below six pixels as a click', () => {
        const element = createElement();
        const onDraggingChange = vi.fn();
        const onMove = vi.fn();
        const onSnap = vi.fn();
        const tracker = createLauncherDragTracker({
            getElement: () => element,
            onDraggingChange,
            onMove,
            onSnap,
        });

        tracker.pointerdown(pointerEvent('pointerdown', 100, 100));
        tracker.pointermove(pointerEvent('pointermove', 105, 100));
        tracker.pointerup(pointerEvent('pointerup', 105, 100));

        expect(onDraggingChange).toHaveBeenLastCalledWith(false);
        expect(onMove).not.toHaveBeenCalled();
        expect(onSnap).not.toHaveBeenCalled();
        const click = new MouseEvent('click', { cancelable: true });
        expect(tracker.clickInterceptor(click)).toBe(false);
        expect(click.defaultPrevented).toBe(false);
    });

    it('tracks a six-pixel drag, snaps, persists, and suppresses the release click', () => {
        const element = createElement();
        const onDraggingChange = vi.fn();
        const onMove = vi.fn();
        const onSnap = vi.fn();
        const tracker = createLauncherDragTracker({
            getElement: () => element,
            onDraggingChange,
            onMove,
            onSnap,
        });

        tracker.pointerdown(pointerEvent('pointerdown', 100, 100));
        tracker.pointermove(pointerEvent('pointermove', 106, 100));

        expect(onDraggingChange).toHaveBeenCalledWith(true);
        expect(onMove).toHaveBeenCalledTimes(1);

        tracker.pointermove(pointerEvent('pointermove', 900, 400));
        tracker.pointerup(pointerEvent('pointerup', 900, 400));

        const result = snapToSide(800, 300);
        expect(onDraggingChange).toHaveBeenLastCalledWith(false);
        expect(onSnap).toHaveBeenCalledWith(result.position, result.coordinates);
        expect(getPersistedPosition()).toEqual(result.position);

        const click = new MouseEvent('click', { cancelable: true, bubbles: true });
        expect(tracker.clickInterceptor(click)).toBe(true);
        expect(click.defaultPrevented).toBe(true);
        expect(tracker.clickInterceptor(new MouseEvent('click', { cancelable: true }))).toBe(false);
    });
});

function createElement(): HTMLElement {
    const element = document.createElement('button');
    element.getBoundingClientRect = () => ({
        x: 0,
        y: 0,
        left: 0,
        top: 0,
        right: 48,
        bottom: 48,
        width: 48,
        height: 48,
        toJSON: () => ({}),
    });
    element.setPointerCapture = vi.fn();
    element.hasPointerCapture = vi.fn(() => true);
    element.releasePointerCapture = vi.fn();
    return element;
}

function pointerEvent(type: string, clientX: number, clientY: number): PointerEvent {
    return {
        type,
        isPrimary: true,
        button: 0,
        pointerId: 1,
        clientX,
        clientY,
    } as PointerEvent;
}
