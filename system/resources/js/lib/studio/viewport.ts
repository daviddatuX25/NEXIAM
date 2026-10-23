/**
 * Pure 2D viewport and zoom math for document studio canvas interactions.
 * Zero domain, framework, or rendering dependencies.
 */

import type {
  CursorZoomOptions,
  CursorZoomResult,
  FitViewportOptions,
} from './types.js';

export const DEFAULT_MIN_ZOOM = 0.25;
export const DEFAULT_MAX_ZOOM = 3.0;
export const DEFAULT_ZOOM_STEP = 0.1;
export const DEFAULT_WHEEL_DELTA_PX = 100;
export const DEFAULT_WHEEL_LINE_HEIGHT = 16;
export const DEFAULT_WHEEL_PAGE_HEIGHT = 800;

/**
 * Convert a WheelEvent delta to CSS pixels.
 *
 * Browsers report pixel, line, or page deltas depending on the input device and
 * platform. Keeping the conversion here makes the zoom response continuous for
 * trackpads while retaining predictable mouse-wheel steps.
 */
export function normalizeWheelDelta(
  delta: number,
  deltaMode = 0,
  viewportSize = DEFAULT_WHEEL_PAGE_HEIGHT
): number {
  const value = Number(delta);
  if (!Number.isFinite(value) || value === 0) return 0;

  const mode = Number(deltaMode);
  const unit =
    mode === 1
      ? DEFAULT_WHEEL_LINE_HEIGHT
      : mode === 2
        ? Math.max(1, Number(viewportSize) || DEFAULT_WHEEL_PAGE_HEIGHT)
        : 1;

  return value * unit;
}

/**
 * Computes a restrained continuous zoom factor from a wheel delta.
 *
 * A 100 CSS-pixel delta produces the requested `step`; smaller trackpad
 * deltas produce proportionally smaller factors, while a single large wheel
 * event is capped at one step to avoid jumps.
 */
export function calculateWheelZoomFactor(
  deltaY: number,
  step = DEFAULT_ZOOM_STEP,
  deltaMode = 0,
  viewportSize = DEFAULT_WHEEL_PAGE_HEIGHT
): number {
  const normalizedDelta = normalizeWheelDelta(deltaY, deltaMode, viewportSize);
  if (normalizedDelta === 0) return 1;

  const base = Math.max(1, 1 + Number(step));
  if (base === 1) return 1;

  const normalizedMagnitude = Math.min(
    1,
    Math.abs(normalizedDelta) / DEFAULT_WHEEL_DELTA_PX
  );
  return Math.pow(base, normalizedDelta < 0 ? normalizedMagnitude : -normalizedMagnitude);
}

/**
 * Clamps a zoom level between minimum and maximum bounds.
 */
export function clampZoom(
  zoom: number,
  minZoom = DEFAULT_MIN_ZOOM,
  maxZoom = DEFAULT_MAX_ZOOM
): number {
  return Math.min(maxZoom, Math.max(minZoom, Number(zoom) || minZoom));
}

/**
 * Calculates cursor-anchored zoom transformation.
 * Preserves the exact canvas point under the cursor before and after scaling,
 * including a changing paper-frame origin (for example, an auto-centered frame
 * whose layout width follows the zoom level):
 *
 * x_new = cursor_x - frame_new_x
 *         - (cursor_x - frame_old_x - pan_x) * (scale_new / scale_old)
 */
export function calculateCursorZoom({
  cursorX,
  cursorY,
  panX,
  panY,
  scaleOld,
  scaleNew,
  frameX = 0,
  frameY = 0,
  nextFrameX = frameX,
  nextFrameY = frameY,
}: CursorZoomOptions): CursorZoomResult {
  if (scaleOld <= 0) {
    return { panX, panY, scale: scaleNew };
  }

  const ratio = scaleNew / scaleOld;
  const localCursorX = cursorX - frameX;
  const localCursorY = cursorY - frameY;
  const newPanX = localCursorX - (localCursorX - panX) * ratio + frameX - nextFrameX;
  const newPanY = localCursorY - (localCursorY - panY) * ratio + frameY - nextFrameY;
  return {
    panX: newPanX,
    panY: newPanY,
    scale: scaleNew,
  };
}

/**
 * Fits a document page within viewport dimensions while respecting gutter and bounds.
 */
export function fitViewport({
  viewportWidth,
  viewportHeight,
  pageWidthPx,
  pageHeightPx,
  minZoom = DEFAULT_MIN_ZOOM,
  maxZoom = DEFAULT_MAX_ZOOM,
  gutter = 36,
}: FitViewportOptions): number {
  if (!viewportWidth || !viewportHeight || !pageWidthPx || !pageHeightPx) {
    return minZoom;
  }
  const availableWidth = Math.max(0, viewportWidth - gutter);
  const availableHeight = Math.max(0, viewportHeight - gutter);
  const fitScale = Math.min(availableWidth / pageWidthPx, availableHeight / pageHeightPx);
  return clampZoom(fitScale, minZoom, maxZoom);
}
/**
 * Keeps desktop page previews readable by prioritizing width and allowing the
 * scrollable canvas to reveal the page's full height.
 */
export function fitViewportForReadableWidth(options: FitViewportOptions): number {
  const {
    viewportWidth,
    viewportHeight,
    pageWidthPx,
    pageHeightPx,
    maxZoom = DEFAULT_MAX_ZOOM,
    minZoom = DEFAULT_MIN_ZOOM,
    gutter = 36,
  } = options;

  if (!viewportWidth || !viewportHeight || !pageWidthPx || !pageHeightPx) {
    return clampZoom(0.8, minZoom, maxZoom);
  }

  const availableWidth = Math.max(0, viewportWidth - gutter);
  const widthFit = availableWidth / pageWidthPx;

  return clampZoom(Math.min(0.85, Math.max(0.75, widthFit)), minZoom, maxZoom);
}
