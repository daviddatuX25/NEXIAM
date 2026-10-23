import {
  cropBox,
  isRecord,
  isRegion,
  toRegion,
  type CropBox,
  type Region,
} from './omrRegionCrop.js';

export type MachineState = 'marked' | 'blank' | 'ambiguous' | 'unreadable';
export type OverlayStatus = 'ready' | 'unavailable';
export type OverlayUnavailableReason =
  | 'missing-machine-evidence'
  | 'missing-item-geometry'
  | 'missing-option-geometry'
  | 'missing-asset'
  | 'missing-asset-dimensions'
  | 'missing-asset-hash'
  | 'missing-evidence-hash'
  | 'missing-geometry-hash'
  | 'missing-evidence-version'
  | 'missing-page'
  | 'missing-crop-transform'
  | 'invalid-crop-transform'
  | 'geometry-out-of-bounds'
  | 'duplicate-option-geometry'
  | 'missing-expected-geometry'
  | 'missing-observed-geometry';

export type MachineObservationClassification =
  | 'marked'
  | 'faint'
  | 'partial'
  | 'erased'
  | 'crossed'
  | 'smudged'
  | 'stray'
  | 'out_of_region'
  | string;

export interface MachineOptionObservation {
  code: string;
  mean: number | null;
  classification: MachineObservationClassification | null;
}

export interface MachineEvidence {
  state: MachineState | null;
  selected_option: string | null;
  observed_options: string[];
  option_observations: MachineOptionObservation[];
  confidence: number | null;
  issue_codes: string[];
  evidence_hash: string | null;
}

export interface ExpectedEvidence {
  option: string | null;
}

export interface ReviewEvidence {
  revision?: number | null;
  decision?: string | null;
  selected_option?: string | null;
  reason_code?: string | null;
  note?: string | null;
  reviewer?: { id?: string | number | null; display_name?: string | null } | null;
  reviewed_at?: string | null;
  evidence_hash?: string | null;
}

export interface AssetProvenance {
  url?: string | null;
  width?: number | null;
  height?: number | null;
  sha256?: string | null;
  page_no?: number | null;
  kind?: 'aligned_page' | 'dedicated_crop' | string | null;
}

export interface CropSourceTransform {
  source_rect?: Region | null;
  rect?: Region | null;
  scale_x?: number | null;
  scale_y?: number | null;
  translate_x?: number | null;
  translate_y?: number | null;
  matrix?: number[] | null;
  valid?: boolean;
}

export interface CropSource {
  kind?: 'aligned_page' | 'dedicated_crop' | string | null;
  type?: 'aligned_page' | 'dedicated_crop' | string | null;
  source_rect?: Region | null;
  rect?: Region | null;
  aligned_rect?: Region | null;
  transform?: CropSourceTransform | null;
  valid?: boolean;
}
export interface OverlayOptionGeometry {
  code: string;
  region: Region;
}

export interface OverlayGeometry {
  item_rect: Region | null;
  crop_rect: Region | null;
  options: OverlayOptionGeometry[];
  aligned_asset: AssetProvenance | null;
  crop_asset: AssetProvenance | null;
  crop_source: CropSource | null;
  geometry_manifest_hash: string | null;
  evidence_version: string | null;
  page_no: number | null;
}

export interface BubbleOverlayInput {
  item?: unknown;
  machine?: unknown;
  expected?: unknown;
  geometry?: unknown;
  review?: unknown;
  asset?: unknown;
  page_asset?: unknown;
  crop_source?: unknown;
  cropUrl?: string | null;
  imageUrl?: string | null;
  region?: unknown;
  crop_rect?: unknown;
  options?: unknown;
  detectedOption?: string | null;
  expectedOption?: string | null;
  state?: string | null;
  evidence_hash?: string | null;
  evidence_version?: string | null;
  page_no?: number | null;
}

export interface NormalizedOverlayInput {
  machine: MachineEvidence | null;
  expected: ExpectedEvidence;
  geometry: OverlayGeometry;
  review: ReviewEvidence | null;
  source_url: string | null;
  image_url: string | null;
  crop_url: string | null;
  legacy: boolean;
}

export type BubbleFillTone =
  | 'match'
  | 'mismatch'
  | 'ambiguous'
  | 'unreadable'
  | 'neutral';

export type BubbleLayerKind = 'machine-fill' | 'candidate-hatch' | 'expected-ring';

export interface OverlayEllipse {
  cx: number;
  cy: number;
  rx: number;
  ry: number;
}

export interface BubbleLayer {
  kind: BubbleLayerKind;
  layer: BubbleLayerKind;
  code: string;
  tone: BubbleFillTone | 'expected';
  geometry: Region;
  ellipse: OverlayEllipse;
  fill: string;
  stroke: string;
  pattern: string | null;
  icon: string;
  aria_label: string;
}

export interface BubbleVisual {
  code: string;
  geometry: Region;
  ellipse: OverlayEllipse;
  fill_tone: BubbleFillTone | null;
  expected_ring: boolean;
  pattern: 'unreadable-hatch' | null;
  icon: string;
  aria_label: string;
}

export interface OverlayReviewSummary {
  reviewed: boolean;
  decision: string | null;
  selected_option: string | null;
  text: string | null;
}

export interface BubbleOverlayRecipe {
  status: OverlayStatus;
  unavailable_reason: OverlayUnavailableReason | null;
  unavailable_reasons: OverlayUnavailableReason[];
  machine_state: MachineState | null;
  expected_option: string | null;
  observed_options: string[];
  viewport: CropBox | null;
  source_rect: Region | null;
  asset: AssetProvenance | null;
  page_no: number | null;
  geometry_manifest_hash: string | null;
  evidence_version: string | null;
  visuals: BubbleVisual[];
  layers: BubbleLayer[];
  state_label: string;
  description: string;
  review: OverlayReviewSummary;
}

const MACHINE_STATES: MachineState[] = [
  'marked',
  'blank',
  'ambiguous',
  'unreadable',
];

const EMPTY_GEOMETRY: OverlayGeometry = {
  item_rect: null,
  crop_rect: null,
  options: [],
  aligned_asset: null,
  crop_asset: null,
  crop_source: null,
  geometry_manifest_hash: null,
  evidence_version: null,
  page_no: null,
};

function stringOr(value: unknown, fallback: string | null = null): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

function numberOr(value: unknown, fallback: number | null = null): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) return parsed;
  }
  return fallback;
}

function positiveNumberOr(value: unknown): number | null {
  const number = numberOr(value, null);
  return number !== null && number > 0 ? number : null;
}

function normalizeHash(value: unknown): string | null {
  const hash = stringOr(value, null)?.toLowerCase() ?? null;
  return hash !== null && /^[a-f0-9]{64}$/.test(hash) ? hash : null;
}

function normalizeState(value: unknown): MachineState | null {
  const state = stringOr(value, null)?.toLowerCase() ?? null;
  if (state === 'match' || state === 'selected' || state === 'exact') return 'marked';
  if (state === 'multiple' || state === 'multi_mark') return 'ambiguous';
  return state && MACHINE_STATES.includes(state as MachineState)
    ? (state as MachineState)
    : null;
}

function normalizeCode(value: unknown): string | null {
  const code = stringOr(value, null);
  return code === null ? null : code;
}

function uniqueCodes(values: unknown): string[] {
  if (!Array.isArray(values)) return [];
  const seen = new Set<string>();
  const codes: string[] = [];
  for (const value of values) {
    const code = normalizeCode(value);
    if (code === null || seen.has(code)) continue;
    seen.add(code);
    codes.push(code);
  }
  return codes;
}

function normalizeObservation(value: unknown): MachineOptionObservation | null {
  if (!isRecord(value)) return null;
  const code = normalizeCode(value.code ?? value.option);
  if (code === null) return null;
  return {
    code,
    mean: numberOr(value.mean ?? value.bubble_mean, null),
    classification: stringOr(value.classification ?? value.threshold_status, null),
  };
}

function normalizeAsset(value: unknown): AssetProvenance | null {
  if (!isRecord(value)) return null;
  const dimensions = isRecord(value.dimensions) ? value.dimensions : null;
  const dimensionsArray = Array.isArray(value.dimensions) ? value.dimensions : null;
  return {
    url: stringOr(value.url ?? value.href, null),
    width: positiveNumberOr(value.width ?? dimensions?.width ?? dimensionsArray?.[0]),
    height: positiveNumberOr(value.height ?? dimensions?.height ?? dimensionsArray?.[1]),
    sha256: normalizeHash(value.sha256 ?? value.hash ?? value.asset_hash),
    page_no: numberOr(value.page_no ?? value.page, null),
    kind: stringOr(value.kind ?? value.type, null),
  };
}

function normalizeTransform(value: unknown): CropSourceTransform | null {
  if (!isRecord(value)) return null;
  let valid = true;
  let matrix: number[] | null = null;
  if ('matrix' in value && value.matrix !== null && value.matrix !== undefined) {
    if (!Array.isArray(value.matrix) || value.matrix.length !== 6) {
      valid = false;
    } else {
      const parsed = value.matrix.map((entry) => numberOr(entry, Number.NaN) as number);
      if (!parsed.every((entry) => Number.isFinite(entry))) {
        valid = false;
      } else {
        const det = parsed[0] * parsed[3] - parsed[1] * parsed[2];
        if (!Number.isFinite(det) || Math.abs(det) < 1e-12) {
          valid = false;
        } else {
          matrix = parsed;
        }
      }
    }
  }
  const rawScaleX = value.scale_x ?? value.scaleX;
  const rawScaleY = value.scale_y ?? value.scaleY;
  const rawTransX = value.translate_x ?? value.translateX;
  const rawTransY = value.translate_y ?? value.translateY;

  const scaleX = rawScaleX !== undefined && rawScaleX !== null ? numberOr(rawScaleX, null) : null;
  const scaleY = rawScaleY !== undefined && rawScaleY !== null ? numberOr(rawScaleY, null) : null;
  const transX = rawTransX !== undefined && rawTransX !== null ? numberOr(rawTransX, null) : null;
  const transY = rawTransY !== undefined && rawTransY !== null ? numberOr(rawTransY, null) : null;

  if (rawScaleX !== undefined && rawScaleX !== null && (scaleX === null || scaleX <= 0 || !Number.isFinite(scaleX))) {
    valid = false;
  }
  if (rawScaleY !== undefined && rawScaleY !== null && (scaleY === null || scaleY <= 0 || !Number.isFinite(scaleY))) {
    valid = false;
  }
  if (rawTransX !== undefined && rawTransX !== null && (transX === null || !Number.isFinite(transX))) {
    valid = false;
  }
  if (rawTransY !== undefined && rawTransY !== null && (transY === null || !Number.isFinite(transY))) {
    valid = false;
  }

  return {
    source_rect: toRegion(value.source_rect),
    rect: toRegion(value.rect),
    scale_x: scaleX,
    scale_y: scaleY,
    translate_x: transX,
    translate_y: transY,
    matrix,
    valid,
  };
}

function normalizeCropSource(value: unknown): CropSource | null {
  if (!isRecord(value)) return null;
  const rawTransform = value.transform;
  const transform = rawTransform !== undefined && rawTransform !== null
    ? normalizeTransform(rawTransform)
    : null;
  const valid = rawTransform !== undefined && rawTransform !== null
    ? (isRecord(rawTransform) && transform?.valid !== false)
    : true;
  return {
    kind: stringOr(value.kind, null),
    type: stringOr(value.type, null),
    source_rect: toRegion(value.source_rect),
    rect: toRegion(value.rect),
    aligned_rect: toRegion(value.aligned_rect),
    transform,
    valid,
  };
}

function normalizeOptions(value: unknown): OverlayOptionGeometry[] {
  if (!Array.isArray(value)) return [];
  const seen = new Set<string>();
  const options: OverlayOptionGeometry[] = [];
  for (const raw of value) {
    if (!isRecord(raw)) continue;
    const code = normalizeCode(raw.code ?? raw.option ?? raw.value);
    const region = toRegion(raw.region ?? raw.rect ?? raw.option_rect ?? raw.geometry);
    if (code === null || region === null) continue;
    if (!seen.has(code)) seen.add(code);
    options.push({ code, region });
  }
  return options;
}

function normalizeMachine(value: unknown, fallback: BubbleOverlayInput): MachineEvidence | null {
  const raw = isRecord(value) ? value : null;
  const state = normalizeState(raw?.state ?? fallback.state);
  const selected = normalizeCode(
    raw?.selected_option ?? raw?.selectedOption ?? raw?.detected_option ?? fallback.detectedOption,
  );
  const observed = uniqueCodes(
    raw?.observed_options ?? raw?.observedOptions ?? raw?.candidates,
  );
  const observedOptions = observed.length > 0
    ? observed
    : state === 'marked' && selected !== null
      ? [selected]
      : [];
  const rawObservations = Array.isArray(
    raw?.option_observations ?? raw?.optionObservations,
  )
    ? (raw?.option_observations ?? raw?.optionObservations) as unknown[]
    : [];
  const observations = rawObservations
    .map(normalizeObservation)
    .filter((entry): entry is MachineOptionObservation => entry !== null);
  if (!raw && state === null && selected === null && observedOptions.length === 0) {
    return null;
  }
  return {
    state,
    selected_option: state === 'unreadable' ? null : selected,
    observed_options: observedOptions,
    option_observations: observations,
    confidence: numberOr(raw?.confidence, null),
    issue_codes: uniqueCodes(raw?.issue_codes ?? raw?.issueCodes),
    evidence_hash: normalizeHash(raw?.evidence_hash ?? raw?.evidenceHash ?? fallback.evidence_hash),
  };
}

function normalizeExpected(value: unknown, fallback: BubbleOverlayInput): ExpectedEvidence {
  if (isRecord(value)) {
    return {
      option: normalizeCode(value.option ?? value.selected_option ?? value.code),
    };
  }
  return {
    option: normalizeCode(value ?? fallback.expectedOption),
  };
}

function normalizeReview(value: unknown): ReviewEvidence | null {
  if (!isRecord(value)) return null;
  const decision = stringOr(value.decision, null);
  if (decision === null) return null;
  const reviewer = isRecord(value.reviewer)
    ? {
        id: value.reviewer.id as string | number | null | undefined,
        display_name: stringOr(value.reviewer.display_name ?? value.reviewer.name, null),
      }
    : null;
  return {
    revision: numberOr(value.revision, null),
    decision,
    selected_option: normalizeCode(value.selected_option ?? value.selectedOption),
    reason_code: stringOr(value.reason_code ?? value.reasonCode, null),
    note: stringOr(value.note, null),
    reviewer,
    reviewed_at: stringOr(value.reviewed_at ?? value.reviewedAt, null),
    evidence_hash: normalizeHash(value.evidence_hash ?? value.evidenceHash),
  };
}


function normalizeGeometry(value: unknown, fallback: BubbleOverlayInput): OverlayGeometry {
  const raw = isRecord(value) ? value : {};
  const itemRect = toRegion(
    raw.item_rect ?? raw.itemRect ?? raw.crop_rect ?? raw.cropRect ?? raw.region ?? fallback.crop_rect ?? fallback.region,
  );
  const rawOptions = raw.options ?? raw.option_rects ?? raw.optionRects ?? fallback.options;
  const cropSource = normalizeCropSource(raw.crop_source ?? raw.cropSource ?? fallback.crop_source);
  const alignedAsset = normalizeAsset(
    raw.aligned_asset ?? raw.alignedAsset ?? raw.aligned ?? fallback.page_asset ?? fallback.asset,
  );
  const cropAsset = normalizeAsset(raw.crop_asset ?? raw.cropAsset);
  const sourceRect = toRegion(
    cropSource?.source_rect ?? cropSource?.rect ?? cropSource?.aligned_rect ?? cropSource?.transform?.source_rect ?? cropSource?.transform?.rect,
  );
  const normalizedCropSource = cropSource
    ? { ...cropSource, source_rect: sourceRect }
    : null;
  return {
    item_rect: itemRect,
    crop_rect: toRegion(raw.crop_rect ?? raw.cropRect ?? itemRect),
    options: normalizeOptions(rawOptions),
    aligned_asset: alignedAsset,
    crop_asset: cropAsset,
    crop_source: normalizedCropSource,
    geometry_manifest_hash: normalizeHash(
      raw.geometry_manifest_hash ?? raw.geometryManifestHash ?? raw.geometry_hash ?? raw.geometryHash ?? raw.manifest_hash,
    ),
    evidence_version: stringOr(raw.evidence_version ?? raw.evidenceVersion ?? fallback.evidence_version, null),
    page_no: numberOr(raw.page_no ?? raw.page ?? fallback.page_no, null),
  };
}

/**
 * Convert the server item/expected/geometry contract into one renderer input.
 * The small legacy branch only keeps existing hosts renderable during the
 * cutover; it never invents candidate geometry or answer-key data.
 */
export function normalizeOverlayInput(value: unknown): NormalizedOverlayInput {
  const root = isRecord(value) ? (value as BubbleOverlayInput & Record<string, unknown>) : {};
  const item = isRecord(root.item) ? root.item : root;
  const machineValue = root.machine ?? item.machine;
  const expectedValue = root.expected ?? item.expected;
  const geometryValue = root.geometry ?? item.geometry;
  const machine = normalizeMachine(machineValue, {
    ...root,
    state: root.state ?? (item.state as string | null | undefined),
    detectedOption: root.detectedOption ?? (item.detected_option as string | null | undefined),
    evidence_hash: root.evidence_hash ?? (item.evidence_hash as string | null | undefined),
  });
  const expected = normalizeExpected(expectedValue, {
    ...root,
    expectedOption: root.expectedOption ?? (item.expected_option as string | null | undefined),
  });
  const geometry = normalizeGeometry(geometryValue, {
    ...root,
    region: root.region ?? item.region,
    crop_rect: root.crop_rect ?? item.crop_rect,
    options: root.options ?? item.options,
    asset: root.asset ?? item.asset,
    page_asset: root.page_asset ?? item.page_asset,
    crop_source: root.crop_source ?? item.crop_source,
    evidence_version: root.evidence_version ?? (item.evidence_version as string | null | undefined),
    page_no: root.page_no ?? (item.page_no as number | null | undefined),
  });
  const review = normalizeReview(root.review ?? item.review);
  const cropUrl = stringOr(root.cropUrl ?? root.crop_url ?? item.cropUrl ?? item.crop_url, null);
  const imageUrl = stringOr(root.imageUrl ?? root.image_url ?? item.imageUrl ?? item.image_url, null);
  const isDedicated = isDedicatedCropSource(geometry.crop_source, geometry.crop_asset, cropUrl);
  const sourceUrl = isDedicated
    ? cropUrl ?? geometry.crop_asset?.url ?? imageUrl ?? geometry.aligned_asset?.url ?? null
    : imageUrl ?? geometry.aligned_asset?.url ?? cropUrl ?? geometry.crop_asset?.url ?? null;
  return {
    machine,
    expected,
    geometry,
    review,
    source_url: sourceUrl,
    image_url: imageUrl,
    crop_url: cropUrl,
    legacy: machineValue == null && geometryValue == null,
  };
}

/** Map a normalized page rectangle into a normalized viewport rectangle. */
export function transformRegionToViewport(region: Region | null, viewport: Region | null): Region | null {
  if (!isRegion(region) || !isRegion(viewport)) return null;
  return {
    x: (region.x - viewport.x) / viewport.w,
    y: (region.y - viewport.y) / viewport.h,
    w: region.w / viewport.w,
    h: region.h / viewport.h,
  };
}

function transformPointWithMatrix(
  x: number,
  y: number,
  matrix: number[],
): { x: number; y: number } {
  return {
    x: matrix[0] * x + matrix[2] * y + matrix[4],
    y: matrix[1] * x + matrix[3] * y + matrix[5],
  };
}

function applySourceTransform(region: Region, transform: CropSourceTransform | null): Region | null {
  if (!transform) return region;
  if (transform.valid === false) return null;
  if (transform.matrix) {
    const corners = [
      transformPointWithMatrix(region.x, region.y, transform.matrix),
      transformPointWithMatrix(region.x + region.w, region.y, transform.matrix),
      transformPointWithMatrix(region.x, region.y + region.h, transform.matrix),
      transformPointWithMatrix(region.x + region.w, region.y + region.h, transform.matrix),
    ];
    const xs = corners.map((corner) => corner.x);
    const ys = corners.map((corner) => corner.y);
    const minX = Math.min(...xs);
    const minY = Math.min(...ys);
    const maxX = Math.max(...xs);
    const maxY = Math.max(...ys);
    const w = maxX - minX;
    const h = maxY - minY;
    if (!Number.isFinite(minX) || !Number.isFinite(minY) || !Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) {
      return null;
    }
    return {
      x: minX,
      y: minY,
      w,
      h,
    };
  }
  const scaleX = numberOr(transform.scale_x, 1) ?? 1;
  const scaleY = numberOr(transform.scale_y, 1) ?? 1;
  const translateX = numberOr(transform.translate_x, 0) ?? 0;
  const translateY = numberOr(transform.translate_y, 0) ?? 0;
  if (
    !Number.isFinite(scaleX) ||
    !Number.isFinite(scaleY) ||
    !Number.isFinite(translateX) ||
    !Number.isFinite(translateY) ||
    scaleX <= 0 ||
    scaleY <= 0
  ) {
    return null;
  }
  const w = region.w * scaleX;
  const h = region.h * scaleY;
  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) {
    return null;
  }
  return {
    x: region.x * scaleX + translateX,
    y: region.y * scaleY + translateY,
    w,
    h,
  };
}

/**
 * Transform an option rectangle through a dedicated crop's aligned-page
 * source rectangle, then through its explicit affine/scale transform.
 */
export function transformOptionRegion(
  region: Region | null,
  sourceRect: Region | null,
  transform: CropSourceTransform | null = null,
): Region | null {
  const local = transformRegionToViewport(region, sourceRect);
  return local ? applySourceTransform(local, transform) : null;
}

function containsRegion(container: Region, region: Region): boolean {
  const epsilon = 1e-7;
  return (
    region.x >= container.x - epsilon &&
    region.y >= container.y - epsilon &&
    region.x + region.w <= container.x + container.w + epsilon &&
    region.y + region.h <= container.y + container.h + epsilon
  );
}

function inUnitRegion(region: Region | null): boolean {
  if (!isRegion(region)) return false;
  const epsilon = 1e-7;
  return (
    region.x >= -epsilon &&
    region.y >= -epsilon &&
    region.x + region.w <= 1 + epsilon &&
    region.y + region.h <= 1 + epsilon
  );
}

function isDedicatedCropSource(
  cropSource: CropSource | null,
  cropAsset: AssetProvenance | null,
  cropUrl: string | null,
): boolean {
  const kind = stringOr(cropSource?.kind ?? cropSource?.type, null)?.toLowerCase();
  return (
    kind === 'dedicated_crop' ||
    cropAsset?.kind === 'dedicated_crop' ||
    (cropUrl !== null && cropUrl !== '')
  );
}

function reviewSummary(review: ReviewEvidence | null): OverlayReviewSummary {
  if (!review?.decision) {
    return { reviewed: false, decision: null, selected_option: null, text: null };
  }
  const selected = review.selected_option ? ` ${review.selected_option}` : '';
  return {
    reviewed: true,
    decision: review.decision,
    selected_option: review.selected_option ?? null,
    text: `Reviewed: ${review.decision}${selected}`,
  };
}

function stateLabel(state: MachineState | null, expected: string | null): string {
  const label = state === 'marked'
    ? 'Marked'
    : state === 'blank'
      ? 'Blank'
      : state === 'ambiguous'
        ? 'Ambiguous'
        : state === 'unreadable'
          ? 'Unreadable'
          : 'Machine state unavailable';
  return expected === null ? `${label}; no expected answer` : label;
}

function iconFor(tone: BubbleFillTone | 'expected', kind: BubbleLayerKind): string {
  if (kind === 'expected-ring') return '○';
  if (tone === 'match') return '✓';
  if (tone === 'mismatch') return '×';
  if (tone === 'ambiguous') return '?';
  if (tone === 'unreadable') return '!';
  return '•';
}

function toneLabel(tone: BubbleFillTone | null, expectedRing: boolean): string {
  const labels: Record<BubbleFillTone, string> = {
    match: 'machine match',
    mismatch: 'machine mismatch',
    ambiguous: 'ambiguous machine candidate',
    unreadable: 'unreadable machine candidate',
    neutral: 'machine observation without expected answer',
  };
  const parts: string[] = [];
  if (tone) parts.push(labels[tone]);
  if (expectedRing) parts.push('expected answer');
  return parts.length > 0 ? parts.join('; ') : 'declared option';
}

function ellipseFor(region: Region, scale: number): OverlayEllipse {
  return {
    cx: region.x + region.w / 2,
    cy: region.y + region.h / 2,
    rx: Math.max(0.0001, region.w * scale),
    ry: Math.max(0.0001, region.h * scale),
  };
}

function makeLayer(
  kind: BubbleLayerKind,
  code: string,
  tone: BubbleFillTone | 'expected',
  geometry: Region,
  ariaLabel: string,
): BubbleLayer {
  const isRing = kind === 'expected-ring';
  return {
    kind,
    layer: kind,
    code,
    tone,
    geometry,
    ellipse: ellipseFor(geometry, isRing ? 0.58 : 0.4),
    fill: isRing ? 'none' : 'currentColor',
    stroke: isRing ? 'currentColor' : 'none',
    pattern: kind === 'candidate-hatch' ? 'unreadable-hatch' : null,
    icon: iconFor(tone, kind),
    aria_label: ariaLabel,
  };
}

function unavailableRecipe(
  input: NormalizedOverlayInput,
  reasons: OverlayUnavailableReason[],
): BubbleOverlayRecipe {
  const machineState = input.machine?.state ?? null;
  const expectedOption = input.expected.option;
  const review = reviewSummary(input.review);
  return {
    status: 'unavailable',
    unavailable_reason: reasons[0] ?? 'missing-machine-evidence',
    unavailable_reasons: reasons,
    machine_state: machineState,
    expected_option: expectedOption,
    observed_options: input.machine?.observed_options ?? [],
    viewport: null,
    source_rect: null,
    asset: null,
    page_no: input.geometry.page_no,
    geometry_manifest_hash: input.geometry.geometry_manifest_hash,
    evidence_version: input.geometry.evidence_version,
    visuals: [],
    layers: [],
    state_label: stateLabel(machineState, expectedOption),
    description: `Evidence unavailable: ${reasons.join(', ')}. Machine evidence remains unchanged.`,
    review,
  };
}

function validateInput(input: NormalizedOverlayInput): {
  reasons: OverlayUnavailableReason[];
  viewport: CropBox | null;
  sourceRect: Region | null;
  asset: AssetProvenance | null;
} {
  const reasons: OverlayUnavailableReason[] = [];
  const machine = input.machine;
  const geometry = input.geometry;
  const itemRect = geometry.item_rect ?? geometry.crop_rect;
  const isDedicated = isDedicatedCropSource(geometry.crop_source, geometry.crop_asset, input.crop_url);

  if (!machine || machine.state === null) {
    reasons.push('missing-machine-evidence');
  } else {
    if (machine.state === 'marked' && (machine.selected_option === null || machine.observed_options.length === 0)) {
      reasons.push('missing-machine-evidence');
    }
    if (machine.state === 'ambiguous' && machine.observed_options.length < 2) {
      reasons.push('missing-machine-evidence');
    }
  }

  if (!itemRect || !isRegion(itemRect)) {
    reasons.push('missing-item-geometry');
  } else if (!inUnitRegion(itemRect)) {
    reasons.push('geometry-out-of-bounds');
  }

  const asset = isDedicated
    ? geometry.crop_asset ?? (input.crop_url ? geometry.aligned_asset : null)
    : geometry.aligned_asset ?? geometry.crop_asset;

  if (!asset) {
    reasons.push('missing-asset');
  } else {
    if (positiveNumberOr(asset.width) === null || positiveNumberOr(asset.height) === null) {
      reasons.push('missing-asset-dimensions');
    }
    if (!normalizeHash(asset.sha256)) {
      reasons.push('missing-asset-hash');
    }
  }

  if (!machine?.evidence_hash) reasons.push('missing-evidence-hash');
  if (!geometry.geometry_manifest_hash) reasons.push('missing-geometry-hash');
  if (!geometry.evidence_version) reasons.push('missing-evidence-version');

  const pageNo = geometry.page_no ?? asset?.page_no ?? null;
  if (pageNo === null || !Number.isInteger(pageNo) || pageNo < 1) {
    reasons.push('missing-page');
  } else if (
    geometry.page_no !== null &&
    asset?.page_no !== null &&
    asset?.page_no !== undefined &&
    geometry.page_no !== asset.page_no
  ) {
    reasons.push('missing-page');
  }

  const options = geometry.options;
  const optionCodes = new Set<string>();
  for (const option of options) {
    if (optionCodes.has(option.code)) reasons.push('duplicate-option-geometry');
    optionCodes.add(option.code);
    if (!inUnitRegion(option.region)) reasons.push('geometry-out-of-bounds');
  }
  if (options.length === 0 && (machine?.observed_options.length || input.expected.option)) {
    reasons.push('missing-option-geometry');
  }
  const expected = input.expected.option;
  if (expected !== null && !optionCodes.has(expected)) reasons.push('missing-expected-geometry');
  for (const code of machine?.observed_options ?? []) {
    if (!optionCodes.has(code)) reasons.push('missing-observed-geometry');
  }

  const cropSource = geometry.crop_source;
  let sourceRect: Region | null = { x: 0, y: 0, w: 1, h: 1 };
  if (cropSource && cropSource.valid === false) {
    reasons.push('invalid-crop-transform');
  }
  if (cropSource?.transform && cropSource.transform.valid === false) {
    reasons.push('invalid-crop-transform');
  }

  if (isDedicated) {
    sourceRect = toRegion(
      cropSource?.source_rect ??
        cropSource?.rect ??
        cropSource?.aligned_rect ??
        cropSource?.transform?.source_rect ??
        cropSource?.transform?.rect,
    );
    if (!sourceRect || !isRegion(sourceRect)) {
      reasons.push('missing-crop-transform');
    } else {
      if (!inUnitRegion(sourceRect)) {
        reasons.push('geometry-out-of-bounds');
      }
      if (itemRect && isRegion(itemRect) && !containsRegion(sourceRect, itemRect)) {
        reasons.push('geometry-out-of-bounds');
      }
    }
  }

  const viewport: CropBox | null = itemRect && isRegion(itemRect)
    ? isDedicated
      ? { x: 0, y: 0, w: 1, h: 1 }
      : cropBox(itemRect)
    : null;
  if (!viewport || !isRegion(viewport)) reasons.push('missing-item-geometry');

  return {
    reasons: [...new Set(reasons)],
    viewport,
    sourceRect,
    asset: asset ?? null,
  };
}

/**
 * Build the complete visual recipe. This is the only place that decides which
 * machine/expected layers exist; Svelte merely maps these layers to SVG.
 */
export function buildBubbleLayerRecipe(value: unknown): BubbleOverlayRecipe {
  const input = normalizeOverlayInput(value);
  const validation = validateInput(input);
  if (validation.reasons.length > 0) return unavailableRecipe(input, validation.reasons);

  const machine = input.machine as MachineEvidence;
  const expectedOption = input.expected.option;
  const viewport = validation.viewport as CropBox;
  const sourceRect = validation.sourceRect as Region;
  const isDedicated = isDedicatedCropSource(
    input.geometry.crop_source,
    input.geometry.crop_asset,
    input.crop_url,
  );
  const transform = input.geometry.crop_source?.transform ?? null;
  const visuals: BubbleVisual[] = [];
  const layers: BubbleLayer[] = [];
  const observed = new Set(machine.observed_options);
  const optionRegions = new Map<string, Region>();

  for (const option of input.geometry.options) {
    const local = isDedicated
      ? transformOptionRegion(option.region, sourceRect, transform)
      : transformRegionToViewport(option.region, viewport);
    if (!local || !inUnitRegion(local)) {
      return unavailableRecipe(input, ['geometry-out-of-bounds']);
    }
    optionRegions.set(option.code, local);
  }

  for (const [code, region] of optionRegions) {
    const isObserved = observed.has(code);
    let fillTone: BubbleFillTone | null = null;
    let pattern: 'unreadable-hatch' | null = null;
    if (machine.state === 'marked' && isObserved && machine.selected_option === code) {
      fillTone = expectedOption === null
        ? 'neutral'
        : code === expectedOption
          ? 'match'
          : 'mismatch';
    } else if (machine.state === 'ambiguous' && isObserved) {
      fillTone = expectedOption === null ? 'neutral' : 'ambiguous';
    } else if (machine.state === 'unreadable' && isObserved) {
      pattern = 'unreadable-hatch';
      fillTone = expectedOption === null ? 'neutral' : 'unreadable';
    }
    const expectedRing = expectedOption === code;
    const ellipse = ellipseFor(region, 0.4);
    const ariaLabel = `Option ${code}: ${toneLabel(fillTone, expectedRing)}`;
    visuals.push({
      code,
      geometry: region,
      ellipse,
      fill_tone: fillTone,
      expected_ring: expectedRing,
      pattern,
      icon: pattern ? '!' : fillTone ? iconFor(fillTone, 'machine-fill') : expectedRing ? '○' : '·',
      aria_label: ariaLabel,
    });
    if (fillTone && pattern === null) {
      layers.push(makeLayer('machine-fill', code, fillTone, region, ariaLabel));
    }
    if (pattern) {
      layers.push(makeLayer('candidate-hatch', code, fillTone ?? 'unreadable', region, ariaLabel));
    }
  }
  for (const visual of visuals) {
    if (!visual.expected_ring) continue;
    layers.push(makeLayer('expected-ring', visual.code, 'expected', visual.geometry, visual.aria_label));
  }

  const state = stateLabel(machine.state, expectedOption);
  const observedLabel = machine.observed_options.length > 0
    ? machine.observed_options.join(', ')
    : 'none';
  const expectedLabel = expectedOption ?? 'none';
  const issuesLabel = machine.issue_codes.length > 0
    ? ` Issues: ${machine.issue_codes.join(', ')}.`
    : '';
  return {
    status: 'ready',
    unavailable_reason: null,
    unavailable_reasons: [],
    machine_state: machine.state,
    expected_option: expectedOption,
    observed_options: machine.observed_options,
    viewport,
    source_rect: sourceRect,
    asset: validation.asset,
    page_no: input.geometry.page_no ?? validation.asset?.page_no ?? null,
    geometry_manifest_hash: input.geometry.geometry_manifest_hash,
    evidence_version: input.geometry.evidence_version,
    visuals,
    layers,
    state_label: state,
    description: `Machine state: ${state}. Observed options: ${observedLabel}. Expected option: ${expectedLabel}.${issuesLabel}`,
    review: reviewSummary(input.review),
  };
}

export function cropAspectRatio(
  viewport: Region | null,
  asset: AssetProvenance | null,
): number | null {
  if (!isRegion(viewport) || !asset) return null;
  const width = positiveNumberOr(asset.width);
  const height = positiveNumberOr(asset.height);
  if (width === null || height === null) return null;
  return (viewport.w * width) / (viewport.h * height);
}

export function overlayLayerClass(layer: BubbleLayer): string {
  if (layer.kind === 'expected-ring') return 'expected-ring';
  if (layer.kind === 'candidate-hatch') return `candidate-hatch ${layer.tone}`;
  return `machine-fill ${layer.tone}`;
}

export function overlayStateIcon(recipe: BubbleOverlayRecipe): string {
  if (recipe.status === 'unavailable') return '□';
  if (recipe.machine_state === 'marked') return '●';
  if (recipe.machine_state === 'blank') return '○';
  if (recipe.machine_state === 'ambiguous') return '?';
  if (recipe.machine_state === 'unreadable') return '!';
  return '·';
}

export function overlayAccessibleText(recipe: BubbleOverlayRecipe): string {
  const review = recipe.review.text ? ` ${recipe.review.text}.` : '';
  return `${recipe.description}${review}`;
}

// Keep the runtime guard local to this module so malformed provider-shaped
// values never leak into SVG arithmetic.
export function isOverlayGeometry(value: unknown): value is OverlayGeometry {
  return isRecord(value) && (value.item_rect === null || isRegion(value.item_rect));
}

export { EMPTY_GEOMETRY };
