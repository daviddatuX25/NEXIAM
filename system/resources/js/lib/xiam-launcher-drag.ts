export type XiamSide = 'left' | 'right';
export type XiamCorner = 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';

export interface XiamLauncherPosition {
    side: XiamSide;
    yRatio: number;
}

const STORAGE_KEY = 'nexiam.xiam.launcher-corner';
const DEFAULT_POSITION: XiamLauncherPosition = { side: 'right', yRatio: 1 };
const DRAG_THRESHOLD_SQUARED = 36;
const EDGE_GAP = 20;
const CORNERS: XiamCorner[] = [
    'bottom-right',
    'bottom-left',
    'top-right',
    'top-left',
];

function normalizePosition(value: unknown): XiamLauncherPosition | null {
    if (
        typeof value !== 'object' ||
        value === null ||
        !('side' in value) ||
        !('yRatio' in value) ||
        (value.side !== 'left' && value.side !== 'right') ||
        typeof value.yRatio !== 'number' ||
        !Number.isFinite(value.yRatio)
    ) {
        return null;
    }

    return {
        side: value.side,
        yRatio: Math.min(1, Math.max(0, value.yRatio)),
    };
}

function legacyPosition(value: string): XiamLauncherPosition | null {
    if (!CORNERS.includes(value as XiamCorner)) {
        return null;
    }

    const corner = value as XiamCorner;
    return {
        side: corner.endsWith('left') ? 'left' : 'right',
        yRatio: corner.startsWith('top') ? 0 : 1,
    };
}

export function getPersistedPosition(key = STORAGE_KEY): XiamLauncherPosition {
    try {
        const value = window.localStorage.getItem(key);
        if (value === null) {
            return { ...DEFAULT_POSITION };
        }

        const legacy = legacyPosition(value);
        if (legacy) {
            return legacy;
        }

        return normalizePosition(JSON.parse(value)) ?? { ...DEFAULT_POSITION };
    } catch {
        return { ...DEFAULT_POSITION };
    }
}

export function persistPosition(position: XiamLauncherPosition, key = STORAGE_KEY): void {
    const normalized = normalizePosition(position);
    if (!normalized) {
        return;
    }

    try {
        window.localStorage.setItem(key, JSON.stringify(normalized));
    } catch {
        // Storage can be unavailable in private or restricted browsing contexts.
    }
}

export function getPersistedCorner(): XiamCorner {
    const position = getPersistedPosition();
    return `${position.yRatio < 0.5 ? 'top' : 'bottom'}-${position.side}` as XiamCorner;
}

export function persistCorner(corner: XiamCorner): void {
    const position = legacyPosition(corner);
    if (position) {
        persistPosition(position);
    }
}

let cachedSafeAreaInsets:
    | { top: number; right: number; bottom: number; left: number }
    | undefined;
let safeAreaProbe: HTMLDivElement | null = null;


function safeAreaInsets(): { top: number; right: number; bottom: number; left: number } {
    if (cachedSafeAreaInsets) {
        return cachedSafeAreaInsets;
    }
    if (typeof document === 'undefined') {
        return { top: 0, right: 0, bottom: 0, left: 0 };
    }

    safeAreaProbe ??= document.createElement('div');
    safeAreaProbe.style.cssText = [
        'position:fixed',
        'visibility:hidden',
        'pointer-events:none',
        'padding-top:env(safe-area-inset-top, 0px)',
        'padding-right:env(safe-area-inset-right, 0px)',
        'padding-bottom:env(safe-area-inset-bottom, 0px)',
        'padding-left:env(safe-area-inset-left, 0px)',
    ].join(';');
    if (!safeAreaProbe.isConnected) {
        document.documentElement.append(safeAreaProbe);
    }
    const style = window.getComputedStyle(safeAreaProbe);
    cachedSafeAreaInsets = {
        top: Number.parseFloat(style.paddingTop) || 0,
        right: Number.parseFloat(style.paddingRight) || 0,
        bottom: Number.parseFloat(style.paddingBottom) || 0,
        left: Number.parseFloat(style.paddingLeft) || 0,
    };
    return cachedSafeAreaInsets;
}

export function refreshSafeAreaInsets(): void {
    cachedSafeAreaInsets = undefined;
}

function viewportBounds() {
    const viewport = window.visualViewport;
    return {
        left: viewport?.offsetLeft ?? 0,
        top: viewport?.offsetTop ?? 0,
        width: viewport?.width ?? window.innerWidth,
        height: viewport?.height ?? window.innerHeight,
    };
}

function bottomDockClearance(viewportBottom: number): number {
    if (typeof document === 'undefined') {
        return EDGE_GAP;
    }

    const selectors = [
        '[data-consultation-mobile-dock]',
        '[data-applicant-mobile-dock]',
        '[data-mobile-dock]',
        '[data-bottom-quick-bar]',
        '[data-mobile-quick-bar]',
        '[data-quick-bar][data-position="bottom"]',
        "nav[aria-label='Release of Results mobile actions']",
        'footer[data-notice-message-action-footer]',
    ];
    let detected = false;
    let clearance = EDGE_GAP;

    for (const selector of selectors) {
        for (const element of document.querySelectorAll<HTMLElement>(selector)) {
            const rect = element.getBoundingClientRect();
            const style = window.getComputedStyle(element);
            if (
                style.display === 'none' ||
                style.visibility === 'hidden' ||
                rect.width <= 0 ||
                rect.height <= 0 ||
                rect.bottom < viewportBottom - 100
            ) {
                continue;
            }

            detected = true;
            clearance = Math.max(clearance, viewportBottom - rect.top + EDGE_GAP);
        }
    }

    return detected ? Math.max(76, clearance) : EDGE_GAP;
}

function launcherBounds(launcherSize: number) {
    const viewport = viewportBounds();
    const insets = safeAreaInsets();
    const mobile = window.innerWidth <= 640;
    const minimumLeft = viewport.left + insets.left + EDGE_GAP;
    const maximumLeft = Math.max(
        minimumLeft,
        viewport.left + viewport.width - insets.right - launcherSize - EDGE_GAP,
    );
    const minimumTop = viewport.top + (mobile ? 68 : 80) + insets.top;
    const maximumTop = Math.max(
        minimumTop,
        viewport.top +
            viewport.height -
            bottomDockClearance(viewport.top + viewport.height) -
            insets.bottom -
            launcherSize,
    );

    return { minimumLeft, maximumLeft, minimumTop, maximumTop };
}

export function calculateLauncherCoordinates(
    position: XiamLauncherPosition,
    launcherSize = 48,
): { left: number; top: number } {
    const { minimumLeft, maximumLeft, minimumTop, maximumTop } =
        launcherBounds(launcherSize);

    return {
        left: position.side === 'left' ? minimumLeft : maximumLeft,
        top: Math.min(
            maximumTop,
            Math.max(
                minimumTop,
                minimumTop + position.yRatio * (maximumTop - minimumTop),
            ),
        ),
    };
}

export function calculateCornerCoordinates(
    corner: XiamCorner,
    launcherSize = 48,
): { left: number; top: number } {
    return calculateLauncherCoordinates(
        legacyPosition(corner) ?? DEFAULT_POSITION,
        launcherSize,
    );
}

export function snapToSide(
    x: number,
    y: number,
    launcherSize = 48,
): {
    position: XiamLauncherPosition;
    coordinates: { left: number; top: number };
} {
    const { minimumLeft, maximumLeft, minimumTop, maximumTop } =
        launcherBounds(launcherSize);
    const side: XiamSide = x < (minimumLeft + maximumLeft) / 2 ? 'left' : 'right';
    const clampedTop = Math.min(maximumTop, Math.max(minimumTop, y));
    const yRatio =
        maximumTop > minimumTop
            ? (clampedTop - minimumTop) / (maximumTop - minimumTop)
            : 1;

    return {
        position: { side, yRatio },
        coordinates: {
            left: side === 'left' ? minimumLeft : maximumLeft,
            top: clampedTop,
        },
    };
}

export function findNearestCorner(
    x: number,
    y: number,
    launcherSize = 48,
): XiamCorner {
    const { position } = snapToSide(x, y, launcherSize);
    return `${position.yRatio < 0.5 ? 'top' : 'bottom'}-${position.side}` as XiamCorner;
}

function clampLauncherCoordinates(
    left: number,
    top: number,
    launcherSize: number,
): { left: number; top: number } {
    const { minimumLeft, maximumLeft, minimumTop, maximumTop } =
        launcherBounds(launcherSize);

    return {
        left: Math.min(maximumLeft, Math.max(minimumLeft, left)),
        top: Math.min(maximumTop, Math.max(minimumTop, top)),
    };
}


export function createLauncherDragTracker({
    getElement,
    storageKey = STORAGE_KEY,
    onDraggingChange,
    onMove,
    onSnap,
}: {
    getElement: () => HTMLElement | null | undefined;
    storageKey?: string;
    onDraggingChange: (isDragging: boolean) => void;
    onMove: (coordinates: { left: number; top: number }) => void;
    onSnap: (
        position: XiamLauncherPosition,
        coordinates: { left: number; top: number },
    ) => void;
}) {
    let pointerId: number | null = null;
    let startX = 0;
    let startY = 0;
    let grabOffsetX = 0;
    let grabOffsetY = 0;
    let isDragging = false;
    let suppressNextClick = false;
    let currentLeft = 0;
    let currentTop = 0;

    function pointerdown(event: PointerEvent): void {
        if (!event.isPrimary || event.button !== 0) {
            return;
        }
        const element = getElement();
        if (!element) {
            return;
        }

        pointerId = event.pointerId;
        startX = event.clientX;
        startY = event.clientY;
        const rect = element.getBoundingClientRect();
        grabOffsetX = event.clientX - rect.left;
        grabOffsetY = event.clientY - rect.top;
        currentLeft = rect.left;
        currentTop = rect.top;
        isDragging = false;
        if (typeof element?.setPointerCapture === 'function') {
            element.setPointerCapture(event.pointerId);
        }
    }

    function pointermove(event: PointerEvent): void {
        if (pointerId !== event.pointerId) {
            return;
        }
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        if (!isDragging && dx * dx + dy * dy >= DRAG_THRESHOLD_SQUARED) {
            isDragging = true;
            onDraggingChange(true);
        }
        if (isDragging) {
            const element = getElement();
            const launcherSize = element?.getBoundingClientRect().width || 48;
            const coordinates = clampLauncherCoordinates(
                event.clientX - grabOffsetX,
                event.clientY - grabOffsetY,
                launcherSize,
            );
            currentLeft = coordinates.left;
            currentTop = coordinates.top;
            onMove(coordinates);
        }
    }

    function finishPointer(event: PointerEvent, shouldSnap: boolean): void {
        if (pointerId !== event.pointerId) {
            return;
        }

        const element = getElement();
        const wasDragging = isDragging;
        pointerId = null;
        isDragging = false;
        onDraggingChange(false);
        if (
            typeof element?.hasPointerCapture === 'function' &&
            element.hasPointerCapture(event.pointerId)
        ) {
            element.releasePointerCapture(event.pointerId);
        } else if (typeof element?.releasePointerCapture === 'function') {
            element.releasePointerCapture(event.pointerId);
        }
        if (!wasDragging || !shouldSnap || !element) {
            return;
        }

        const rect = element.getBoundingClientRect();
        const result = snapToSide(currentLeft, currentTop, rect.width || 48);
        suppressNextClick = true;
        persistPosition(result.position, storageKey);
        onSnap(result.position, result.coordinates);
    }

    function pointerup(event: PointerEvent): void {
        finishPointer(event, true);
    }

    function pointercancel(event: PointerEvent): void {
        finishPointer(event, false);
    }

    function clickInterceptor(event: MouseEvent): boolean {
        if (!suppressNextClick) {
            return false;
        }
        event.preventDefault();
        event.stopPropagation();
        suppressNextClick = false;
        return true;
    }

    return { pointerdown, pointermove, pointerup, pointercancel, clickInterceptor };
}
