export const ALLOWED_SCALES = [100, 115, 125] as const;

export type ScalePercent = (typeof ALLOWED_SCALES)[number];

const scaleToPx: Record<ScalePercent, string> = {
    100: '14px',
    115: '16.1px',
    125: '17.5px',
};

let currentScale = $state<ScalePercent>(100);

export function getScale(): ScalePercent {
    return currentScale;
}

export function setScale(percent: ScalePercent, persist = true): void {
    currentScale = percent;
    if (typeof document !== 'undefined') {
        document.documentElement.style.fontSize = scaleToPx[percent];
    }
    if (persist) {
        try {
            localStorage.setItem('app_scale', String(percent));
        } catch {
            // Ignore storage write restrictions
        }
    }
}

export function resetScale(): void {
    setScale(100);
}

export function zoomIn(): void {
    if (currentScale === 100) {
        setScale(115);
    } else if (currentScale === 115) {
        setScale(125);
    }
}

export function zoomOut(): void {
    if (currentScale === 125) {
        setScale(115);
    } else if (currentScale === 115) {
        setScale(100);
    }
}

export function canZoomIn(): boolean {
    return currentScale < 125;
}

export function canZoomOut(): boolean {
    return currentScale > 100;
}

export function initializeScale(): void {
    let storedScale: string | null = null;

    try {
        storedScale = localStorage.getItem('app_scale');
    } catch {
        // Storage may be unavailable; use the default scale.
    }

    const scale =
        ALLOWED_SCALES.find(
            (allowedScale) => String(allowedScale) === storedScale,
        ) ?? 100;
    currentScale = scale;
    if (typeof document !== 'undefined') {
        document.documentElement.style.fontSize = scaleToPx[scale];
    }
}
