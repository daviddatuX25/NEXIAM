export const STUDIO_SCHEMA_VERSION = 1;
/**
 * Shared context key for the authenticated host's active Studio workflow.
 *
 * A Symbol keeps the context private to the application while remaining stable
 * when this module is evaluated through more than one bundle entry.
 */
export const STUDIO_HOST_CONTEXT = Symbol.for('nexiam.studio-host-context');

const NUMERIC_STAGE_FIELDS = new Set([
  'current',
  'index',
  'next',
  'nextId',
  'nextStage',
  'nextStageId',
  'number',
  'position',
  'prev',
  'prevId',
  'previous',
  'previousId',
  'previousStage',
  'previousStageId',
  'total',
]);

function hasNumericStageField(value) {
  return Object.keys(value).some((key) => NUMERIC_STAGE_FIELDS.has(key));
}

/**
 * Normalize and validate the host-owned workflow registration.
 *
 * Stage IDs are intentionally treated as opaque strings. Unknown descriptor
 * properties are dropped so numeric or domain-specific stage state cannot
 * accidentally cross the host boundary.
 */
export function normalizeStudioStageRegistration(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value) || hasNumericStageField(value)) {
    return null;
  }

  const currentId = value.currentId;
  const descriptors = value.descriptors;
  if (
    typeof currentId !== 'string' ||
    currentId.trim() === '' ||
    !Array.isArray(descriptors) ||
    descriptors.length === 0
  ) {
    return null;
  }

  const seenIds = new Set();
  const normalizedDescriptors = [];
  for (const descriptor of descriptors) {
    if (
      !descriptor ||
      typeof descriptor !== 'object' ||
      Array.isArray(descriptor) ||
      hasNumericStageField(descriptor) ||
      typeof descriptor.id !== 'string' ||
      descriptor.id.trim() === '' ||
      seenIds.has(descriptor.id) ||
      typeof descriptor.label !== 'string' ||
      descriptor.label.trim() === '' ||
      typeof descriptor.activate !== 'function'
    ) {
      return null;
    }

    if (
      (descriptor.description !== undefined && typeof descriptor.description !== 'string') ||
      (descriptor.disabled !== undefined && typeof descriptor.disabled !== 'boolean')
    ) {
      return null;
    }

    seenIds.add(descriptor.id);
    normalizedDescriptors.push({
      id: descriptor.id,
      label: descriptor.label,
      ...(descriptor.description === undefined ? {} : { description: descriptor.description }),
      ...(descriptor.disabled === undefined ? {} : { disabled: descriptor.disabled }),
      activate: descriptor.activate,
    });
  }

  if (!seenIds.has(currentId)) return null;

  return Object.freeze({
    currentId,
    descriptors: Object.freeze(normalizedDescriptors.map((descriptor) => Object.freeze(descriptor))),
  });
}

export function validateStudioStageRegistration(value) {
  return normalizeStudioStageRegistration(value) !== null;
}

/**
 * Tokenized single-owner registration store used by StaffLayout.
 *
 * Invalid registrations and stale token operations are no-ops. This lets a
 * route transition safely overlap child teardown and the next child mount.
 */
export function createStudioStageHost(onChange = () => {}) {
  let active = null;

  const emit = () => {
    onChange(active?.registration ?? null);
  };

  return {
    register(value) {
      const registration = normalizeStudioStageRegistration(value);
      if (!registration) return null;

      const token = Symbol('studio-host-registration');
      active = { token, registration };
      emit();
      return token;
    },

    update(token, value) {
      if (!active || token !== active.token) return false;
      const registration = normalizeStudioStageRegistration(value);
      if (!registration) return false;

      active.registration = registration;
      emit();
      return true;
    },

    unregister(token) {
      if (!active || token !== active.token) return false;

      active = null;
      emit();
      return true;
    },

    get() {
      return active?.registration ?? null;
    },
  };
}


export function clamp(value, min, max) {
  return Math.min(Math.max(Number(value) || 0, min), max);
}

export function pageDimensions(paperSize = 'a4', orientation = 'portrait') {
  const base = paperSize === 'legal'
    ? { width_mm: 216, height_mm: 356 }
    : paperSize === 'letter'
      ? { width_mm: 216, height_mm: 279 }
      : { width_mm: 210, height_mm: 297 };

  return orientation === 'landscape'
    ? { width_mm: base.height_mm, height_mm: base.width_mm }
    : base;
}

export function normalizePage(page = {}) {
  const paperSize = ['a4', 'legal', 'letter'].includes(page.paper_size) ? page.paper_size : 'a4';
  const orientation = ['portrait', 'landscape'].includes(page.orientation) ? page.orientation : 'portrait';
  const dimensions = pageDimensions(paperSize, orientation);

  return {
    paper_size: paperSize,
    orientation,
    width_mm: dimensions.width_mm,
    height_mm: dimensions.height_mm,
    margin_mm: clamp(page.margin_mm ?? 12, 5, 30),
  };
}

/** Viewport contract shared by document studios: fit the page without mutating document geometry. */
export function fitViewport({ viewportWidth, viewportHeight, pageWidthPx, pageHeightPx, minZoom = 0.18, maxZoom = 1.1, gutter = 36 }) {
  if (!viewportWidth || !viewportHeight || !pageWidthPx || !pageHeightPx) return minZoom;
  return Math.min(maxZoom, Math.max(minZoom, Math.min((viewportWidth - gutter) / pageWidthPx, (viewportHeight - gutter) / pageHeightPx)));
}

export function resetViewport() {
  return { panX: 0, panY: 0 };
}

export function componentRect(component) {
  return {
    x: Number(component?.x ?? 0),
    y: Number(component?.y ?? 0),
    w: Math.max(1, Number(component?.w ?? 1)),
    h: Math.max(1, Number(component?.h ?? 1)),
  };
}

export function overlaps(a, b) {
  const first = componentRect(a);
  const second = componentRect(b);
  return first.x < second.x + second.w
    && first.x + first.w > second.x
    && first.y < second.y + second.h
    && first.y + first.h > second.y;
}
