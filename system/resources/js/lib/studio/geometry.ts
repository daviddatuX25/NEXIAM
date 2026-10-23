/**
 * Pure 2D geometry functions in millimetres for document studio canvas interactions.
 * Zero domain, framework, or rendering dependencies.
 */

import type {
  AlignmentTarget,
  AxisLockResult,
  Bounds,
  DistributionAxis,
  IdentifiedRect,
  Point,
  Rect,
  SnapGuide,
  SnapResult,
} from './types.js';

/**
 * Normalizes a rectangle so width and height are non-negative.
 * Essential for marquee drag boxes drawn in negative directions.
 */
export function normalizeRect(rect: Rect): Rect {
  const x = rect.width < 0 ? rect.x + rect.width : rect.x;
  const y = rect.height < 0 ? rect.y + rect.height : rect.y;
  const width = Math.abs(rect.width);
  const height = Math.abs(rect.height);

  return { x, y, width, height };
}

/**
 * Checks if two axis-aligned rectangles intersect (collision detection for marquee selection).
 */
export function rectIntersects(a: Rect, b: Rect): boolean {
  const na = normalizeRect(a);
  const nb = normalizeRect(b);

  return (
    na.x < nb.x + nb.width &&
    na.x + na.width > nb.x &&
    na.y < nb.y + nb.height &&
    na.y + na.height > nb.y
  );
}

/**
 * Checks if the inner rectangle is completely contained within the container rectangle.
 */
export function rectContains(container: Rect, inner: Rect): boolean {
  const nc = normalizeRect(container);
  const ni = normalizeRect(inner);

  return (
    ni.x >= nc.x &&
    ni.y >= nc.y &&
    ni.x + ni.width <= nc.x + nc.width &&
    ni.y + ni.height <= nc.y + nc.height
  );
}

/**
 * Checks if a 2D point lies within a rectangle.
 */
export function pointInRect(point: Point, rect: Rect): boolean {
  const nr = normalizeRect(rect);

  return (
    point.x >= nr.x &&
    point.x <= nr.x + nr.width &&
    point.y >= nr.y &&
    point.y <= nr.y + nr.height
  );
}

/**
 * Clamps a numeric scalar between a minimum and maximum value.
 */
export function clamp(value: number, min: number, max: number): number {
  if (min > max) {
    return min;
  }
  return Math.min(Math.max(value, min), max);
}

/**
 * Normalizes bounds input whether specified as Rect ({x, y, width, height})
 * or Bounds ({minX, minY, maxX, maxY}).
 */
function toExplicitBounds(bounds: Rect | Bounds): Bounds {
  if ('minX' in bounds && 'minY' in bounds && 'maxX' in bounds && 'maxY' in bounds) {
    return {
      minX: Math.min(bounds.minX, bounds.maxX),
      minY: Math.min(bounds.minY, bounds.maxY),
      maxX: Math.max(bounds.minX, bounds.maxX),
      maxY: Math.max(bounds.minY, bounds.maxY),
    };
  }

  const rect = bounds as Rect;
  const normalized = normalizeRect(rect);
  return {
    minX: normalized.x,
    minY: normalized.y,
    maxX: normalized.x + normalized.width,
    maxY: normalized.y + normalized.height,
  };
}

/**
 * Clamps a rectangle within specified printable or container boundaries.
 * Constrains width/height if larger than bounds, and clamps coordinates.
 */
export function clampToBounds(rect: Rect, bounds: Rect | Bounds): Rect {
  const normRect = normalizeRect(rect);
  const explicitBounds = toExplicitBounds(bounds);

  const availableWidth = Math.max(0, explicitBounds.maxX - explicitBounds.minX);
  const availableHeight = Math.max(0, explicitBounds.maxY - explicitBounds.minY);

  const clampedWidth = Math.min(normRect.width, availableWidth);
  const clampedHeight = Math.min(normRect.height, availableHeight);

  const maxX = explicitBounds.maxX - clampedWidth;
  const maxY = explicitBounds.maxY - clampedHeight;

  const clampedX = clamp(normRect.x, explicitBounds.minX, maxX);
  const clampedY = clamp(normRect.y, explicitBounds.minY, maxY);

  return {
    x: clampedX,
    y: clampedY,
    width: clampedWidth,
    height: clampedHeight,
  };
}

/**
 * Constrains movement deltas to the dominant axis when Shift modifier is active.
 * If movement is below threshold, returns { dx: 0, dy: 0 } when threshold is positive.
 */
export function lockAxis(dx: number, dy: number, threshold = 0): AxisLockResult {
  const absDx = Math.abs(dx);
  const absDy = Math.abs(dy);

  if (threshold > 0 && absDx < threshold && absDy < threshold) {
    return { dx: 0, dy: 0 };
  }

  return absDx >= absDy ? { dx, dy: 0 } : { dx: 0, dy };
}

/**
 * Computes the minimum collective bounding box enclosing all input rectangles.
 * Returns null if the array is empty.
 */
export function getBoundingBox(rects: readonly Rect[]): Rect | null {
  if (rects.length === 0) {
    return null;
  }

  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;

  for (const rect of rects) {
    const nr = normalizeRect(rect);
    if (nr.x < minX) minX = nr.x;
    if (nr.y < minY) minY = nr.y;
    if (nr.x + nr.width > maxX) maxX = nr.x + nr.width;
    if (nr.y + nr.height > maxY) maxY = nr.y + nr.height;
  }

  return {
    x: minX,
    y: minY,
    width: maxX - minX,
    height: maxY - minY,
  };
}

/**
 * Calculates the delta (dx, dy) needed to align a rect to a reference rect.
 */
export function getAlignmentDelta(
  rect: Rect,
  reference: Rect,
  alignment: AlignmentTarget
): { dx: number; dy: number } {
  const target = normalizeRect(rect);
  const ref = normalizeRect(reference);

  switch (alignment) {
    case 'left':
      return { dx: ref.x - target.x, dy: 0 };
    case 'center':
      return {
        dx: ref.x + ref.width / 2 - (target.x + target.width / 2),
        dy: 0,
      };
    case 'right':
      return {
        dx: ref.x + ref.width - (target.x + target.width),
        dy: 0,
      };
    case 'top':
      return { dx: 0, dy: ref.y - target.y };
    case 'middle':
      return {
        dx: 0,
        dy: ref.y + ref.height / 2 - (target.y + target.height / 2),
      };
    case 'bottom':
      return {
        dx: 0,
        dy: ref.y + ref.height - (target.y + target.height),
      };
  }
}

/**
 * Aligns a collection of identified rectangles to a reference rect
 * (or to the collective bounding box if no reference is provided).
 * Returns a new array with updated coordinates; does not mutate input.
 */
export function alignRects<T extends IdentifiedRect>(
  items: readonly T[],
  alignment: AlignmentTarget,
  reference?: Rect
): T[] {
  if (items.length <= 1 && !reference) {
    return items.slice();
  }

  const ref = reference ?? getBoundingBox(items);
  if (!ref) {
    return items.slice();
  }

  return items.map((item) => {
    const delta = getAlignmentDelta(item, ref, alignment);
    return {
      ...item,
      x: item.x + delta.dx,
      y: item.y + delta.dy,
    };
  });
}

/**
 * Evenly distributes items along the specified axis between the first and last item.
 * Preserves original item order in the returned array while updating positions.
 */
export function distributeRects<T extends IdentifiedRect>(
  items: readonly T[],
  axis: DistributionAxis
): T[] {
  if (items.length < 3) {
    return items.slice();
  }

  if (axis === 'horizontal') {
    // Sort items by X position
    const sorted = items
      .map((item, index) => ({ item, index, norm: normalizeRect(item) }))
      .sort((a, b) => a.norm.x - b.norm.x);

    const first = sorted[0];
    const last = sorted[sorted.length - 1];

    const innerSpan = last.norm.x - (first.norm.x + first.norm.width);
    const intermediateWidths = sorted
      .slice(1, -1)
      .reduce((sum, current) => sum + current.norm.width, 0);

    const totalGap = innerSpan - intermediateWidths;
    const gap = totalGap / (sorted.length - 1);

    const newPositions = new Map<string, number>();
    let currentX = first.norm.x;

    for (let i = 0; i < sorted.length; i++) {
      newPositions.set(sorted[i].item.id, currentX);
      currentX += sorted[i].norm.width + gap;
    }

    return items.map((item) => {
      const newX = newPositions.get(item.id);
      return newX !== undefined ? { ...item, x: newX } : { ...item };
    });
  } else {
    // Sort items by Y position
    const sorted = items
      .map((item, index) => ({ item, index, norm: normalizeRect(item) }))
      .sort((a, b) => a.norm.y - b.norm.y);

    const first = sorted[0];
    const last = sorted[sorted.length - 1];

    const innerSpan = last.norm.y - (first.norm.y + first.norm.height);
    const intermediateHeights = sorted
      .slice(1, -1)
      .reduce((sum, current) => sum + current.norm.height, 0);

    const totalGap = innerSpan - intermediateHeights;
    const gap = totalGap / (sorted.length - 1);

    const newPositions = new Map<string, number>();
    let currentY = first.norm.y;

    for (let i = 0; i < sorted.length; i++) {
      newPositions.set(sorted[i].item.id, currentY);
      currentY += sorted[i].norm.height + gap;
    }

    return items.map((item) => {
      const newY = newPositions.get(item.id);
      return newY !== undefined ? { ...item, y: newY } : { ...item };
    });
  }
}

/**
 * Calculates smart snap guides and adjusted position for a dragged rectangle
 * against other stationary rectangles.
 */
export function calculateSnapGuides(
  draggedRect: Rect,
  otherRects: readonly Rect[],
  snapTolerance = 2
): SnapResult {
  const dragged = normalizeRect(draggedRect);
  let bestDx: number | null = null;
  let bestDy: number | null = null;
  let bestSnapGuideX: SnapGuide | null = null;
  let bestSnapGuideY: SnapGuide | null = null;

  const draggedLeft = dragged.x;
  const draggedCenterX = dragged.x + dragged.width / 2;
  const draggedRight = dragged.x + dragged.width;

  const draggedTop = dragged.y;
  const draggedCenterY = dragged.y + dragged.height / 2;
  const draggedBottom = dragged.y + dragged.height;

  for (const other of otherRects) {
    const target = normalizeRect(other);
    const targetLeft = target.x;
    const targetCenterX = target.x + target.width / 2;
    const targetRight = target.x + target.width;

    const targetTop = target.y;
    const targetCenterY = target.y + target.height / 2;
    const targetBottom = target.y + target.height;

    const guideOriginY = Math.min(draggedTop, targetTop);
    const guideTargetY = Math.max(draggedBottom, targetBottom);

    const guideOriginX = Math.min(draggedLeft, targetLeft);
    const guideTargetX = Math.max(draggedRight, targetRight);

    // --- Horizontal (X) Snap Candidates ---
    const xSnaps: Array<{ delta: number; guide: SnapGuide }> = [
      // Left to Left
      {
        delta: targetLeft - draggedLeft,
        guide: { axis: 'x', position: targetLeft, origin: guideOriginY, target: guideTargetY },
      },
      // Left to Right
      {
        delta: targetRight - draggedLeft,
        guide: { axis: 'x', position: targetRight, origin: guideOriginY, target: guideTargetY },
      },
      // Center to Center
      {
        delta: targetCenterX - draggedCenterX,
        guide: { axis: 'x', position: targetCenterX, origin: guideOriginY, target: guideTargetY },
      },
      // Right to Left
      {
        delta: targetLeft - draggedRight,
        guide: { axis: 'x', position: targetLeft, origin: guideOriginY, target: guideTargetY },
      },
      // Right to Right
      {
        delta: targetRight - draggedRight,
        guide: { axis: 'x', position: targetRight, origin: guideOriginY, target: guideTargetY },
      },
    ];

    for (const snap of xSnaps) {
      const absDelta = Math.abs(snap.delta);
      if (absDelta <= snapTolerance) {
        if (bestDx === null || absDelta < Math.abs(bestDx)) {
          bestDx = snap.delta;
          bestSnapGuideX = snap.guide;
        }
      }
    }

    // --- Vertical (Y) Snap Candidates ---
    const ySnaps: Array<{ delta: number; guide: SnapGuide }> = [
      // Top to Top
      {
        delta: targetTop - draggedTop,
        guide: { axis: 'y', position: targetTop, origin: guideOriginX, target: guideTargetX },
      },
      // Top to Bottom
      {
        delta: targetBottom - draggedTop,
        guide: { axis: 'y', position: targetBottom, origin: guideOriginX, target: guideTargetX },
      },
      // Center to Center
      {
        delta: targetCenterY - draggedCenterY,
        guide: { axis: 'y', position: targetCenterY, origin: guideOriginX, target: guideTargetX },
      },
      // Bottom to Top
      {
        delta: targetTop - draggedBottom,
        guide: { axis: 'y', position: targetTop, origin: guideOriginX, target: guideTargetX },
      },
      // Bottom to Bottom
      {
        delta: targetBottom - draggedBottom,
        guide: { axis: 'y', position: targetBottom, origin: guideOriginX, target: guideTargetX },
      },
    ];

    for (const snap of ySnaps) {
      const absDelta = Math.abs(snap.delta);
      if (absDelta <= snapTolerance) {
        if (bestDy === null || absDelta < Math.abs(bestDy)) {
          bestDy = snap.delta;
          bestSnapGuideY = snap.guide;
        }
      }
    }
  }

  const snappedRect: Rect = {
    x: dragged.x + (bestDx ?? 0),
    y: dragged.y + (bestDy ?? 0),
    width: dragged.width,
    height: dragged.height,
  };

  const guides: SnapGuide[] = [];
  if (bestSnapGuideX) guides.push(bestSnapGuideX);
  if (bestSnapGuideY) guides.push(bestSnapGuideY);

  return { snappedRect, guides };
}
