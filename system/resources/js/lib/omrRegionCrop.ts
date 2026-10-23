// Shared normalized crop geometry for OMR diagnostic evidence.
//
// This module deliberately owns only page/crop coordinates and object guards.
// Bubble state/layer decisions live in omrBubbleEvidenceOverlay.ts so a crop
// cannot accidentally become a second detector or mutate machine evidence.

export interface Region {
  x: number;
  y: number;
  w: number;
  h: number;
}

/** A usable region has positive finite width and height. */
export function isRegion(value: unknown): value is Region {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const region = value as Record<string, unknown>;
  return (
    typeof region.x === 'number' &&
    typeof region.y === 'number' &&
    typeof region.w === 'number' &&
    typeof region.h === 'number' &&
    Number.isFinite(region.x) &&
    Number.isFinite(region.y) &&
    Number.isFinite(region.w) &&
    Number.isFinite(region.h) &&
    region.w > 0 &&
    region.h > 0
  );
}

/** Canonical object guard: non-null, non-array object. Fields stay unknown. */
export function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

/** Tolerant region reader: malformed, missing, or zero-sized values become null. */
export function toRegion(value: unknown): Region | null {
  return isRegion(value) ? value : null;
}

export interface CropBox extends Region {}

/**
 * Padded normalized box for a row-level crop: generous left pad so the row
 * label is visible, small pads on the other sides, clamped to the page.
 */
export function cropBox(region: Region | null): CropBox | null {
  if (!isRegion(region)) return null;
  const padLeft = region.w * 0.22;
  const padRight = region.w * 0.05;
  const padTop = region.h * 0.25;
  const padBottom = region.h * 0.2;
  const x = Math.max(0, region.x - padLeft);
  const y = Math.max(0, region.y - padTop);
  const w = Math.min(1 - x, region.w + padLeft + padRight);
  const h = Math.min(1 - y, region.h + padTop + padBottom);
  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) return null;
  return { x, y, w, h };
}

/** Canonical name used by the SVG workbench for the padded crop viewport. */
export function cropViewport(region: Region | null): CropBox | null {
  return cropBox(region);
}

export type {
  AssetProvenance,
  BubbleFillTone,
  BubbleLayer,
  BubbleLayerKind,
  BubbleOverlayInput,
  BubbleOverlayRecipe,
  BubbleVisual,
  CropSource,
  CropSourceTransform,
  ExpectedEvidence,
  MachineEvidence,
  MachineObservationClassification,
  MachineOptionObservation,
  MachineState,
  NormalizedOverlayInput,
  OverlayGeometry,
  OverlayOptionGeometry,
  OverlayReviewSummary,
  OverlayStatus,
  OverlayUnavailableReason,
  ReviewEvidence,
} from './omrBubbleEvidenceOverlay.js';

export {
  buildBubbleLayerRecipe,
  cropAspectRatio,
  isOverlayGeometry,
  normalizeOverlayInput,
  overlayAccessibleText,
  overlayLayerClass,
  overlayStateIcon,
  transformOptionRegion,
  transformRegionToViewport,
} from './omrBubbleEvidenceOverlay.js';
