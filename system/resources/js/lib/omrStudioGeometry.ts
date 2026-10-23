/**
 * OMR Template Studio Geometry & Conversion Engine
 *
 * Physical authority at 300 PPI: 1 mm = 300 / 25.4 ≈ 11.811 px.
 * Transforms parametric studio blocks into native OMRChecker mapping
 * payloads and fixed overlay boxes.
 */

export const TARGET_PPI = 300;
export const MM_PER_INCH = 25.4;
export const PX_PER_MM = TARGET_PPI / MM_PER_INCH; // ~11.8110236

export const MIN_BUBBLE_MM = 4.2;
export const MIN_UPLOADED_BUBBLE_MM = 2.5;
export const OMR_BUBBLE_DIAMETER_MM = 4.5;
export const MIN_OPTION_GAP_MM = 1.26;
export const OMR_BUBBLE_PITCH_MM = OMR_BUBBLE_DIAMETER_MM + MIN_OPTION_GAP_MM;
export const GRID_BUBBLE_ORIGIN_OFFSET_MM = { x: 5.0, y: 8.0 } as const;
export const ROLL_BUBBLE_ORIGIN_OFFSET_MM = { x: 6.4, y: 14.0 } as const;
export const ALLOWED_INSET_RANGE_MM: [number, number] = [3.0, 40.0];
export const MIN_STUDIO_SCALE = 0.35;
export const MAX_STUDIO_SCALE = 6.0;

/**
 * Calculates optimal in-bubble font size and layout for recognition guides.
 * Bubble circles are rendered at 2.476 CSS px/mm on screen before sheet scaling.
 */
export function bubbleLabelLayout(label: string, diameterMm: number) {
    const rawDiameterPx = Math.max(1, (Number(diameterMm) || 4.5) * 2.476);
    // Inner diameter after subtracting 1px border on both sides (2px total)
    const innerDiameterPx = Math.max(0, rawDiameterPx - 2);
    const cleanLabel = String(label ?? '').trim();
    const len = cleanLabel.length;

    if (len === 0) {
        return { fitsInside: false, fontSizePx: 0, showInside: false };
    }

    // Monospace / system font width per character is ~0.55 to 0.6 of font size.
    // For single chars (len <= 1), fits inside if inner diameter >= 3.0px
    if (len === 1) {
        const optimalSize = Math.max(3.5, Math.min(8.0, innerDiameterPx * 0.75));
        const fitsInside = innerDiameterPx >= 3.0;
        return {
            fitsInside,
            fontSizePx: Number(optimalSize.toFixed(1)),
            showInside: fitsInside,
        };
    }

    // For 2 characters (e.g. "Q1", "10", "TF"):
    if (len === 2) {
        const optimalSize = Math.max(4.0, Math.min(6.5, (innerDiameterPx * 0.85) / 1.3));
        const fitsInside = innerDiameterPx >= 6.5;
        return {
            fitsInside,
            fontSizePx: Number(optimalSize.toFixed(1)),
            showInside: fitsInside,
        };
    }

    // For 3+ characters (e.g. "A-B", "a-b", "TRUE"):
    // Check if inner diameter is large enough:
    const estCharWidthFactor = 0.58;
    const maxFontForWidth = innerDiameterPx / (len * estCharWidthFactor);
    const optimalSize = Math.min(6.0, maxFontForWidth);
    const fitsInside = optimalSize >= 4.0 && innerDiameterPx >= 10.0;

    return {
        fitsInside,
        fontSizePx: Number(Math.max(4.0, optimalSize).toFixed(1)),
        showInside: fitsInside,
    };
}

export interface PaperDefinition {
    name: string;
    width_mm: number;
    height_mm: number;
    default_margin_mm: number;
}

export const STANDARD_PAPERS: Record<string, PaperDefinition> = {
    a4: { name: 'A4', width_mm: 210, height_mm: 297, default_margin_mm: 15 },
    legal: {
        name: 'Legal (Long Bond)',
        width_mm: 216,
        height_mm: 330,
        default_margin_mm: 15,
    },
    letter: {
        name: 'Letter (Short Bond)',
        width_mm: 216,
        height_mm: 279,
        default_margin_mm: 15,
    },
};

export function mmToPx(mm: number): number {
    return Math.round(mm * PX_PER_MM);
}

export function pxToMm(px: number): number {
    return Number((px / PX_PER_MM).toFixed(2));
}

export type RecognitionGeometryKind = 'grid' | 'roll';

export interface EffectiveRecognitionGeometryMm {
    bubbleDiameterMm: number;
    bubblePitchMm: number;
    rowPitchMm: number;
}

/** Smallest center-to-center pitch that preserves the native option edge gap. */
export function minimumRecognitionPitchMm(
    bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM,
    minBubbleMm = MIN_BUBBLE_MM,
): number {
    const bubble = Number(bubbleDiameterMm);
    const effectiveBubble =
        Number.isFinite(bubble) && bubble > 0
            ? Math.max(minBubbleMm, bubble)
            : OMR_BUBBLE_DIAMETER_MM;

    return Number((effectiveBubble + MIN_OPTION_GAP_MM).toFixed(2));
}

/**
 * Resolve one requested center-to-center pitch against the native OMR safety gap.
 * The fallback preserves the existing kind-specific defaults when a value is absent.
 */
export function effectiveRecognitionPitchMm(
    pitchMm: number | null | undefined,
    bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM,
    fallbackMm = OMR_BUBBLE_DIAMETER_MM + MIN_OPTION_GAP_MM,
    minBubbleMm = MIN_BUBBLE_MM,
): number {
    const requested = Number(pitchMm);
    const fallback = Number(fallbackMm);
    const minimum = minimumRecognitionPitchMm(
        bubbleDiameterMm,
        minBubbleMm,
    );
    const requestedOrFallback =
        Number.isFinite(requested) && requested > 0
            ? requested
            : Number.isFinite(fallback) && fallback > 0
              ? fallback
              : minimum;

    return Math.max(minimum, requestedOrFallback);
}

/**
 * Canonical recognition dimensions consumed by the native emitter and canvas.
 * Every editable pitch, including persisted legacy values, passes through this helper.
 */
export function effectiveRecognitionGeometryMm(input: {
    kind: RecognitionGeometryKind;
    bubbleDiameterMm?: number | null;
    bubblePitchMm?: number | null;
    rowPitchMm?: number | null;
    minBubbleMm?: number;
}): EffectiveRecognitionGeometryMm {
    const bubble = Number(input.bubbleDiameterMm);
    const minBubble = input.minBubbleMm ?? MIN_BUBBLE_MM;
    const bubbleDiameterMm =
        Number.isFinite(bubble) && bubble > 0
            ? Math.max(minBubble, bubble)
            : OMR_BUBBLE_DIAMETER_MM;
    const defaults =
        input.kind === 'roll'
            ? { bubblePitchMm: 5.5, rowPitchMm: 4.8 }
            : { bubblePitchMm: 6.0, rowPitchMm: 6.8 };

    return {
        bubbleDiameterMm,
        bubblePitchMm: effectiveRecognitionPitchMm(
            input.bubblePitchMm,
            bubbleDiameterMm,
            defaults.bubblePitchMm,
            minBubble,
        ),
        rowPitchMm: effectiveRecognitionPitchMm(
            input.rowPitchMm,
            bubbleDiameterMm,
            defaults.rowPitchMm,
            minBubble,
        ),
    };
}

/**
 * Content-derived block bounds for a question section.
 *
 * The builder no longer hand-resizes section rectangles (constrained by design);
 * bounds are derived purely from bubble/row pitch and question/option counts so
 * the canvas rect always hugs its bubbles and the native mapping stays in sync.
 *
 * `minW_mm`/`minH_mm` mirror the canvas preview heuristic (14 = bubble diameter,
 * 8/10 = inner padding, 50 = max rendered rows).
 */
export function contentBlockSizeMm(
    options: number,
    questions: number,
    bubblePitchMm: number,
    rowPitchMm: number,
    bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM,
    minBubbleMm?: number,
): { w_mm: number; h_mm: number } {
    const opts = Math.max(2, options || 4);
    const qs = Math.min(Math.max(1, questions || 1), 50);
    const geometry = effectiveRecognitionGeometryMm({
        kind: 'grid',
        bubbleDiameterMm,
        bubblePitchMm,
        rowPitchMm,
        minBubbleMm,
    });
    const {
        bubbleDiameterMm: bubble,
        bubblePitchMm: bPitch,
        rowPitchMm: rPitch,
    } = geometry;
    // Physical bounds: shared bubble diameter + option pitch span + 1.5mm padding.
    // Satisfies OmrMappingValidator requiredWidth (bubbleWidth + (options - 1) * optionPitch)
    // with zero inter-block overlap.
    const w_mm = Number((bubble + (opts - 1) * bPitch + 1.5).toFixed(1));
    const h_mm = Number((bubble + (qs - 1) * rPitch + 1.5).toFixed(1));

    return { w_mm, h_mm };
}

export interface InsetMargins {
    top_mm: number;
    right_mm: number;
    bottom_mm: number;
    left_mm: number;
}

export interface StudioBlock {
    id: string;
    type:
        | 'mcq'
        | 'tf'
        | 'likert'
        | 'custom'
        | 'roll'
        | 'qr'
        | 'text'
        | 'header'
        | 'signature'
        | 'instructions'
        | 'metadata_box'
        | 'custom_text';
    page_no?: number;
    x_mm: number;
    y_mm: number;
    w_mm: number;
    h_mm: number;
    // Question configuration
    area_code?: string;
    subtest_code?: string;
    test_instrument_subtest_id?: number;
    questions?: number;
    cols?: number;
    options?: number;
    start_q?: number;
    custom_values?: string[];
    optionLabels?: string[];
    bubble_values?: string[];
    bubble_pitch_mm?: number;
    row_pitch_mm?: number;
    option_points_mm?: Array<{ label: string; x_mm: number; y_mm: number }>;
    rotation_deg?: number;
    identity_grid?: boolean;
    roll_direction?: 'vertical' | 'horizontal';
    // Roll specific
    digits?: number;
    tag?: string;
    scope?: string;
    overflow?: 'truncate' | 'shrink' | 'wrap';
    font_size?: number;
    title?: string;
    content?: string;
}

export interface NativeMappingBlock {
    name: string;
    page_no: number;
    field_type: string;
    label_prefix: string;
    labels: string[];
    rows: number;
    options: number;
    subtest_code?: string;
    test_instrument_subtest_id?: number;
    bubble_values?: string[];
    direction?: string;
    origin_x: number;
    origin_y: number;
    width: number;
    height: number;
    option_pitch_px: number;
    row_pitch_px: number;
    bubble_width_px: number;
    bubble_height_px: number;
    bubble_positions?: number[][][];
}

export interface NativeMappingPayload {
    page_width: number;
    page_height: number;
    page_count: number;
    blocks: NativeMappingBlock[];
}

export interface OverlayBox {
    name?: string;
    page_no: number;
    x: number;
    y: number;
    width: number;
    height: number;
    tag: string;
    scope: string;
    overflow: 'truncate' | 'shrink' | 'wrap';
    font_size?: number | null;
    content: string | null;
}

/**
 * Rotate integer-pixel bubble top-lefts around the first bubble's center.
 * Rounding occurs once, after the rigid rotation, for canvas/native parity.
 */
export function rotateBubblePositionsPx(
    rows: number[][][],
    bubbleWidthPx: number,
    angleDeg: number,
): number[][][] {
    if (angleDeg === 0) {
        return rows;
    }

    const first = rows[0]?.[0];
    if (!first || !Number.isFinite(angleDeg)) {
        return rows;
    }

    const centerX = first[0] + bubbleWidthPx / 2;
    const centerY = first[1] + bubbleWidthPx / 2;
    const radians = (angleDeg * Math.PI) / 180;
    const cos = Math.cos(radians);
    const sin = Math.sin(radians);

    return rows.map((row) =>
        row.map(([x, y]) => {
            const dx = x + bubbleWidthPx / 2 - centerX;
            const dy = y + bubbleWidthPx / 2 - centerY;
            return [
                Math.round(centerX + dx * cos - dy * sin - bubbleWidthPx / 2),
                Math.round(centerY + dx * sin + dy * cos - bubbleWidthPx / 2),
            ];
        }),
    );
}

/** Build digit-column × value-row pixel positions for the vertical shaded grid. */
export function verticalRollBubblePositionsPx(input: {
    originX: number;
    originY: number;
    digitCount: number;
    digitPitchPx: number;
    valuePitchPx: number;
    bubbleWidthPx: number;
    rotationDeg?: number;
}): number[][][] {
    const rows = Array.from({ length: input.digitCount }, (_, digitIndex) =>
        Array.from({ length: 10 }, (_, value) => [
            input.originX + digitIndex * input.digitPitchPx,
            input.originY + value * input.valuePitchPx,
        ]),
    );

    return rotateBubblePositionsPx(
        rows,
        input.bubbleWidthPx,
        input.rotationDeg ?? 0,
    );
}


export interface StudioValidationWarning {
    block_id?: string;
    code: string;
    message: string;
    severity: 'warning' | 'error';
}

export interface StudioValidationResult {
    valid: boolean;
    warnings: StudioValidationWarning[];
}

/** Position of the first native bubble inside the visual component footprint. */
export function recognitionOriginOffsetMm(block: StudioBlock): {
    x: number;
    y: number;
} {
    return block.type === 'roll'
        ? ROLL_BUBBLE_ORIGIN_OFFSET_MM
        : GRID_BUBBLE_ORIGIN_OFFSET_MM;
}

interface SnapSection {
    id?: string;
    type?: string;
    page_no?: number;
    locked?: boolean;
    isLocked?: boolean;
    optionLabels?: string[];
    customValuesStr?: string;
    optionsCount?: number;
    questionsCount?: number;
    bubbleDiameterMm?: number;
    bubblePitchMm?: number;
    rowPitchMm?: number;
    optionPointsMm?: Array<{ label?: string; x_mm: number; y_mm: number }>;
    posX?: number;
    posY?: number;
    rotationDeg?: number;
    [key: string]: unknown;
}

interface SnapBubbleSeed {
    sectionIndex: number;
    questionIndex: number;
    optionIndex: number;
    label: string;
    xMm: number;
    yMm: number;
    radiusMm: number;
}

interface SnapQuestionRow {
    sectionIndex: number;
    questionIndex: number;
    bubbles: SnapBubbleSeed[];
    xMm: number;
    yMm: number;
    coarseDx: number;
    coarseDy: number;
    matched: boolean;
}

interface SnappedOptionPoint {
    label: string;
    x_mm: number;
    y_mm: number;
}

function median(values: number[]): number {
    if (values.length === 0) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    const middle = Math.floor(sorted.length / 2);
    return sorted.length % 2 === 0
        ? (sorted[middle - 1] + sorted[middle]) / 2
        : sorted[middle];
}


/**
 * Aligns programmed bubble centers to printed circle borders without mutating input.
 */
export function snapSectionsToPrintedCircles<T extends SnapSection>(
    imageData: {
        data: Uint8ClampedArray | Uint8Array;
        width: number;
        height: number;
    },
    sections: T[],
    options: {
        pageNo?: number;
        paperWidthMm?: number;
        paperHeightMm?: number;
        minBubbleMm?: number;
        adjustDiameter?: boolean;
        searchRadiusMm?: number;
    } = {},
): {
    sections: T[];
    snappedQuestionsCount: number;
    snappedBubblesCount: number;
} {
    const { width, height, data } = imageData;
    if (width <= 0 || height <= 0 || data.length === 0) {
        return { sections: sections.map((section) => ({ ...section })), snappedQuestionsCount: 0, snappedBubblesCount: 0 };
    }
    const scaleX = width / (options.paperWidthMm ?? 210);
    const scaleY = height / (options.paperHeightMm ?? 297);
    const gray = new Float32Array(width * height);
    const isRgba = data.length >= width * height * 4;
    for (let index = 0; index < gray.length; index++) {
        const value = isRgba
            ? 0.299 * data[index * 4] +
              0.587 * data[index * 4 + 1] +
              0.114 * data[index * 4 + 2]
            : data[index];
        gray[index] = 255 - value;
    }

    const pageNo = Number(options.pageNo);
    const rows: SnapQuestionRow[] = [];
    const optionLabelsFor = (section: SnapSection): string[] => {
        if (Array.isArray(section.optionLabels) && section.optionLabels.length > 0) {
            return section.optionLabels.map(String);
        }
        if (typeof section.customValuesStr === 'string') {
            const labels = section.customValuesStr.split(',').map((label) => label.trim()).filter(Boolean);
            if (labels.length > 0) return labels;
        }
        if (Array.isArray(section.optionPointsMm)) {
            const pointLabels = [
                ...new Set(
                    section.optionPointsMm
                        .map((point) => String(point.label ?? '').trim())
                        .filter(Boolean),
                ),
            ];
            if (pointLabels.length > 0) return pointLabels;
        }
        const count = Math.max(2, Number(section.optionsCount) || 4);
        return Array.from({ length: count }, (_, index) => String.fromCharCode(65 + index));
    };
    sections.forEach((section, sectionIndex) => {
        const sectionPageNo = Number(section.page_no) || 1;
        if (
            (Number.isFinite(pageNo) && pageNo > 0 && sectionPageNo !== pageNo) ||
            section.locked === true ||
            section.isLocked === true ||
            section.type === 'roll'
        ) {
            return;
        }
        const labels = optionLabelsFor(section);
        const questionCount = Math.max(1, Number(section.questionsCount) || 1);
        const diameterMm =
            Number(section.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
        const radiusMm = diameterMm / 2;
        const pitch = effectiveRecognitionGeometryMm({
            kind: 'grid',
            bubbleDiameterMm: diameterMm,
            bubblePitchMm: section.bubblePitchMm,
            rowPitchMm: section.rowPitchMm,
            minBubbleMm: options.minBubbleMm,
        });
        const explicitPoints =
            Array.isArray(section.optionPointsMm) &&
            section.optionPointsMm.length === questionCount * labels.length
                ? section.optionPointsMm
                : null;
        const origin = GRID_BUBBLE_ORIGIN_OFFSET_MM;
        for (let questionIndex = 0; questionIndex < questionCount; questionIndex++) {
            const bubbles = labels.map((label, optionIndex) => {
                const point = explicitPoints?.[questionIndex * labels.length + optionIndex];
                const xMm = point && Number.isFinite(Number(point.x_mm))
                    ? Number(point.x_mm)
                    : (Number(section.posX) || 0) + origin.x + radiusMm + optionIndex * pitch.bubblePitchMm;
                const yMm = point && Number.isFinite(Number(point.y_mm))
                    ? Number(point.y_mm)
                    : (Number(section.posY) || 0) + origin.y + radiusMm + questionIndex * pitch.rowPitchMm;
                return { sectionIndex, questionIndex, optionIndex, label: String(point?.label ?? label), xMm, yMm, radiusMm };
            });
            rows.push({
                sectionIndex,
                questionIndex,
                bubbles,
                xMm: median(bubbles.map((bubble) => bubble.xMm)),
                yMm: median(bubbles.map((bubble) => bubble.yMm)),
                coarseDx: 0,
                coarseDy: 0,
                matched: false,
            });
        }
    });

    const radiusScale = Math.sqrt(scaleX * scaleY);
    const requestedRadius = options.searchRadiusMm === undefined ? 5 : Number(options.searchRadiusMm);
    const searchRadiusMm = Math.max(2, Math.min(12, Number.isFinite(requestedRadius) ? requestedRadius : 5));
    const limitX = Math.ceil(searchRadiusMm * scaleX);
    const limitY = Math.ceil(searchRadiusMm * scaleY);
    const coarseStep = Math.max(1, Math.round(0.4 * Math.min(scaleX, scaleY)));
    const ringSampleCount = 48;
    const sectorSampleCount = 8;
    type RingTable = {
        ringOffsets: Float64Array;
        haloOffsets: Float64Array;
        inwardOffsets: Float64Array;
        shell55Offsets: Float64Array;
        shell78Offsets: Float64Array;
        extent: number;
    };
    const radiusKeys = new Set<number>();
    for (const row of rows) {
        for (const bubble of row.bubbles) {
            const radius = bubble.radiusMm * radiusScale;
            if (options.adjustDiameter === false) {
                radiusKeys.add(Math.round(radius * 2) / 2);
                continue;
            }
            for (let key = Math.floor(radius * 0.7 * 2); key <= Math.ceil(radius * 1.3 * 2); key++) {
                if (key >= 4) radiusKeys.add(key / 2);
            }
        }
    }
    const offsetTables = new Map<number, RingTable>();
    for (const radius of radiusKeys) {
        const samples = ringSampleCount + 3 * sectorSampleCount;
        const ringOffsets = new Float64Array(samples * 2);
        const haloOffsets = new Float64Array(samples * 2);
        const inwardOffsets = new Float64Array(samples * 2);
        const shell55Offsets = new Float64Array(samples * 2);
        const shell78Offsets = new Float64Array(samples * 2);
        const inwardRadius = Math.max(2, radius - Math.max(2.5, radius * 0.18));
        for (let sample = 0; sample < samples; sample++) {
            let degrees: number;
            if (sample < ringSampleCount) {
                degrees = -45 + (270 * sample) / ringSampleCount;
            } else {
                const sector = Math.floor((sample - ringSampleCount) / sectorSampleCount);
                const index = (sample - ringSampleCount) % sectorSampleCount;
                degrees = [-35, 55, 145][sector] + (70 * index) / (sectorSampleCount - 1);
            }
            const angle = (degrees * Math.PI) / 180;
            const cosine = Math.cos(angle);
            const sine = Math.sin(angle);
            ringOffsets[sample * 2] = cosine * radius;
            ringOffsets[sample * 2 + 1] = sine * radius;
            haloOffsets[sample * 2] = cosine * (radius + 2.5);
            haloOffsets[sample * 2 + 1] = sine * (radius + 2.5);
            inwardOffsets[sample * 2] = cosine * inwardRadius;
            inwardOffsets[sample * 2 + 1] = sine * inwardRadius;
            shell55Offsets[sample * 2] = cosine * radius * 0.55;
            shell55Offsets[sample * 2 + 1] = sine * radius * 0.55;
            shell78Offsets[sample * 2] = cosine * radius * 0.78;
            shell78Offsets[sample * 2 + 1] = sine * radius * 0.78;
        }
        offsetTables.set(radius, {
            ringOffsets,
            haloOffsets,
            inwardOffsets,
            shell55Offsets,
            shell78Offsets,
            extent: Math.ceil(radius + 3),
        });
    }
    const tileShift = 7;
    const tileSize = 1 << tileShift;
    const tileMask = tileSize - 1;
    const tilesX = Math.ceil(width / tileSize);
    const contrastCache = new Map<number, Map<number, Float32Array>>();
    const contrast = (cx: number, cy: number, radius: number): number => {
        const radiusKey = Math.round(radius * 2) / 2;
        const table = offsetTables.get(radiusKey);
        if (!table || !Number.isFinite(cx) || !Number.isFinite(cy)) return 0;
        if (cx < table.extent || cy < table.extent || cx >= width - table.extent || cy >= height - table.extent) return 0;
        const ix = Math.round(cx);
        const iy = Math.round(cy);
        const isInteger = Math.abs(cx - ix) < 1e-3 && Math.abs(cy - iy) < 1e-3;
        let tile: Float32Array | undefined;
        let cellIndex = 0;
        if (isInteger) {
            let radiusTiles = contrastCache.get(radiusKey);
            if (!radiusTiles) {
                radiusTiles = new Map<number, Float32Array>();
                contrastCache.set(radiusKey, radiusTiles);
            }
            const tileKey = (iy >> tileShift) * tilesX + (ix >> tileShift);
            tile = radiusTiles.get(tileKey);
            if (!tile) {
                tile = new Float32Array(tileSize * tileSize);
                tile.fill(-1);
                radiusTiles.set(tileKey, tile);
            }
            cellIndex = ((iy & tileMask) << tileShift) | (ix & tileMask);
            if (tile[cellIndex] >= 0) return tile[cellIndex];
        }
        const ring = table.ringOffsets;
        const halo = table.haloOffsets;
        const inward = table.inwardOffsets;
        let ringTotal = 0;
        let haloTotal = 0;
        let run = 0;
        let longestRun = 0;
        for (let sample = 0; sample < ringSampleCount; sample++) {
            const offset = sample * 2;
            const rx = Math.round(cx + ring[offset]);
            const ry = Math.round(cy + ring[offset + 1]);
            const hx = Math.round(cx + halo[offset]);
            const hy = Math.round(cy + halo[offset + 1]);
            const innerX = Math.round(cx + inward[offset]);
            const innerY = Math.round(cy + inward[offset + 1]);
            const darkness = gray[ry * width + rx];
            const outside = gray[hy * width + hx];
            const inside = gray[innerY * width + innerX];
            ringTotal += darkness;
            haloTotal += outside;
            if (darkness - outside >= 8 && darkness - inside >= 8) {
                run++;
                if (run > longestRun) longestRun = run;
            } else {
                run = 0;
            }
        }
        const raw = (ringTotal - haloTotal) / ringSampleCount;
        let shell55Total = 0;
        let shell78Total = 0;
        if (raw >= 6) {
            for (let sample = 0; sample < ringSampleCount; sample++) {
                const offset = sample * 2;
                const x55 = Math.round(cx + table.shell55Offsets[offset]);
                const y55 = Math.round(cy + table.shell55Offsets[offset + 1]);
                const x78 = Math.round(cx + table.shell78Offsets[offset]);
                const y78 = Math.round(cy + table.shell78Offsets[offset + 1]);
                shell55Total += gray[y55 * width + x55];
                shell78Total += gray[y78 * width + x78];
            }
        }
        const lighterShellMean = Math.min(shell55Total, shell78Total) / ringSampleCount;
        const hasLighterInterior = ringTotal / ringSampleCount - lighterShellMean >= 8;
        let score = 0;
        if (raw >= 6 && hasLighterInterior) {
            let sectorMask = 0;
            for (let sector = 0; sector < 3; sector++) {
                let total = 0;
                for (let sample = 0; sample < sectorSampleCount; sample++) {
                    const offset = (ringSampleCount + sector * sectorSampleCount + sample) * 2;
                    const rx = Math.round(cx + ring[offset]);
                    const ry = Math.round(cy + ring[offset + 1]);
                    const hx = Math.round(cx + halo[offset]);
                    const hy = Math.round(cy + halo[offset + 1]);
                    total += gray[ry * width + rx] - gray[hy * width + hx];
                }
                if (total / sectorSampleCount >= 4) sectorMask |= 1 << sector;
            }
            // Bits: right=1, bottom=2, left=4. Mask 5 is two opposed lobes
            // without bottom evidence, a displaced-circle/chord ambiguity.
            if (sectorMask === 7 || (sectorMask !== 0 && sectorMask !== 5 && longestRun >= 5)) score = raw;
        }
        if (tile) tile[cellIndex] = score;
        return score;
    };
    const currentBoundaryContrast = (cx: number, cy: number, radius: number): number => {
        const table = offsetTables.get(Math.round(radius * 2) / 2);
        if (!table || !Number.isFinite(cx) || !Number.isFinite(cy)) return 0;
        if (
            cx < table.extent || cy < table.extent ||
            cx >= width - table.extent || cy >= height - table.extent
        ) return 0;
        for (let sector = 0; sector < 3; sector++) {
            let sectorTotal = 0;
            for (let sample = 0; sample < sectorSampleCount; sample++) {
                const offset = (ringSampleCount + sector * sectorSampleCount + sample) * 2;
                const rx = Math.round(cx + table.ringOffsets[offset]);
                const ry = Math.round(cy + table.ringOffsets[offset + 1]);
                const hx = Math.round(cx + table.haloOffsets[offset]);
                const hy = Math.round(cy + table.haloOffsets[offset + 1]);
                sectorTotal += gray[ry * width + rx] - gray[hy * width + hx];
            }
            if (sectorTotal / sectorSampleCount < 4) return 0;
        }
        let ringTotal = 0;
        let haloTotal = 0;
        let shell55Total = 0;
        let shell78Total = 0;
        for (let sample = 0; sample < ringSampleCount; sample++) {
            const offset = sample * 2;
            const rx = Math.round(cx + table.ringOffsets[offset]);
            const ry = Math.round(cy + table.ringOffsets[offset + 1]);
            const hx = Math.round(cx + table.haloOffsets[offset]);
            const hy = Math.round(cy + table.haloOffsets[offset + 1]);
            const x55 = Math.round(cx + table.shell55Offsets[offset]);
            const y55 = Math.round(cy + table.shell55Offsets[offset + 1]);
            const x78 = Math.round(cx + table.shell78Offsets[offset]);
            const y78 = Math.round(cy + table.shell78Offsets[offset + 1]);
            ringTotal += gray[ry * width + rx];
            haloTotal += gray[hy * width + hx];
            shell55Total += gray[y55 * width + x55];
            shell78Total += gray[y78 * width + x78];
        }
        const raw = (ringTotal - haloTotal) / ringSampleCount;
        const hollow = (ringTotal - Math.min(shell55Total, shell78Total)) / ringSampleCount;
        return raw >= 8 && hollow >= 8 ? raw : 0;
    };

    const fitPrintedRingCenter = (
        seedX: number,
        seedY: number,
        radius: number,
    ): { x: number; y: number; radius: number } | null => {
        if (!Number.isFinite(seedX) || !Number.isFinite(seedY) || radius < 2) return null;
        const band = Math.max(1.5, Math.min(2.5, radius * 0.1));
        let centerX = seedX;
        let centerY = seedY;
        let measuredRadius = radius;
        for (let iteration = 0; iteration < 3; iteration++) {
            let weight = 0;
            let sumX = 0;
            let sumY = 0;
            let sumXX = 0;
            let sumXY = 0;
            let sumYY = 0;
            let sumQ = 0;
            let sumXQ = 0;
            let sumYQ = 0;
            let sectors = 0;
            const left = Math.max(0, Math.floor(centerX - radius - band));
            const right = Math.min(width - 1, Math.ceil(centerX + radius + band));
            const top = Math.max(0, Math.floor(centerY - radius - band));
            const bottom = Math.min(height - 1, Math.ceil(centerY + radius + band));
            for (let py = top; py <= bottom; py++) {
                const y = py - centerY;
                for (let px = left; px <= right; px++) {
                    const x = px - centerX;
                    if (y < 0 && Math.abs(x) < -y) continue;
                    if (Math.abs(Math.hypot(x, y) - radius) > band) continue;
                    const darkness = gray[py * width + px];
                    if (darkness < 32) continue;
                    const w = darkness / 255;
                    const q = x * x + y * y;
                    weight += w;
                    sumX += w * x;
                    sumY += w * y;
                    sumXX += w * x * x;
                    sumXY += w * x * y;
                    sumYY += w * y * y;
                    sumQ += w * q;
                    sumXQ += w * x * q;
                    sumYQ += w * y * q;
                    if (x < -radius * 0.35) sectors |= 1;
                    if (x > radius * 0.35) sectors |= 2;
                    if (y > radius * 0.35) sectors |= 4;
                }
            }
            if (weight < 12 || sectors !== 7) return null;
            const xx = sumXX - (sumX * sumX) / weight;
            const xy = sumXY - (sumX * sumY) / weight;
            const yy = sumYY - (sumY * sumY) / weight;
            const xq = (sumXQ - (sumX * sumQ) / weight) / 2;
            const yq = (sumYQ - (sumY * sumQ) / weight) / 2;
            const determinant = xx * yy - xy * xy;
            if (xx <= 0 || yy <= 0 || determinant <= xx * yy * 1e-6) return null;
            const dx = (xq * yy - yq * xy) / determinant;
            const dy = (yq * xx - xq * xy) / determinant;
            const fittedRadiusSquared =
                sumQ / weight - (2 * (dx * sumX + dy * sumY)) / weight + dx * dx + dy * dy;
            const nextX = centerX + dx;
            const nextY = centerY + dy;
            if (
                !Number.isFinite(nextX) || !Number.isFinite(nextY) ||
                fittedRadiusSquared <= 0 ||
                Math.abs(Math.sqrt(fittedRadiusSquared) - radius) > band ||
                Math.hypot(nextX - seedX, nextY - seedY) > 1.2
            ) return null;
            measuredRadius = Math.sqrt(fittedRadiusSquared);
            centerX = nextX;
            centerY = nextY;
            if (Math.hypot(dx, dy) < 1e-6) break;
        }
        return { x: centerX, y: centerY, radius: measuredRadius };
    };

    type Candidate = {
        dx: number;
        dy: number;
        pitchScale: number;
        contrast: number;
        strongBubbleCount: number;
        score: number;
        targetX: number;
        targetY: number;
        radiusPx?: number;
    };
    const initialRadiusProbes = new Map<SnapQuestionRow, number[]>();
    const candidateRadiusProbes = new Map<SnapQuestionRow, readonly number[]>();
    const measuredBySection = new Map<number, number[]>();
    const measuredByColumn = new Map<SnapQuestionRow[], number[]>();
    for (const row of rows) {
        const nominal = median(row.bubbles.map((bubble) => bubble.radiusMm * radiusScale));
        const preferred = Math.round(nominal * 2) / 2;
        const probes: number[] = [preferred];
        if (options.adjustDiameter !== false) {
            for (const factor of [0.90, 1.10, 0.82, 1.18]) {
                const key = Math.round(nominal * factor * 2) / 2;
                if (key >= 2 && offsetTables.has(key) && !probes.includes(key)) {
                    probes.push(key);
                }
            }
        }
        initialRadiusProbes.set(row, probes);
    }
    const currentCandidates = new Map<SnapQuestionRow, Candidate>();
    const changedSectionIndices = new Set<number>();
    for (const row of rows) {
        if (row.bubbles.length < 2) continue;
        const values: number[] = [];
        const measuredRadii: number[] = [];
        let complete = true;
        for (const bubble of row.bubbles) {
            const cx = bubble.xMm * scaleX;
            const cy = bubble.yMm * scaleY;
            const bestRadius = Math.round(bubble.radiusMm * radiusScale * 2) / 2;
            const best = currentBoundaryContrast(cx, cy, bestRadius);
            if (best < 12) {
                complete = false;
                break;
            }
            const fitted = fitPrintedRingCenter(cx, cy, bestRadius);
            const measured = fitted && currentBoundaryContrast(cx, cy, fitted.radius) >= 8
                ? fitted.radius
                : bestRadius;
            measuredRadii.push(Math.round(measured * 2) / 2);
            values.push(best);
        }
        if (!complete) continue;
        values.sort((a, b) => a - b);
        const trim = Math.floor(values.length * 0.2);
        let total = 0;
        for (let index = trim; index < values.length - trim; index++) total += values[index];
        const middle = Math.floor(values.length / 2);
        const medianValue = values.length % 2 === 0
            ? (values[middle - 1] + values[middle]) / 2
            : values[middle];
        const robust = 0.7 * total / (values.length - 2 * trim) + 0.3 * medianValue;
        if (robust < 12) continue;
        currentCandidates.set(row, {
            dx: 0,
            dy: 0,
            pitchScale: 1,
            contrast: robust,
            strongBubbleCount: row.bubbles.length,
            score: Math.min(1, robust / 64),
            targetX: row.xMm * scaleX,
            targetY: row.yMm * scaleY,
            radiusPx: median(measuredRadii),
        });
    }
    const candidateAtRadius = (
        row: SnapQuestionRow,
        dx: number,
        dy: number,
        pitchScale: number,
        radiusPx: number,
    ): Candidate | null => {
        if (Math.hypot(dx / scaleX, dy / scaleY) > searchRadiusMm) return null;
        const anchorX = row.bubbles[0].xMm;
        const ringScore = row.bubbles.length === 2 ? currentBoundaryContrast : contrast;
        const values: number[] = [];
        let distance = 0;
        let supported = 0;
        let strong = 0;
        for (const bubble of row.bubbles) {
            const x = Math.round((anchorX + (bubble.xMm - anchorX) * pitchScale) * scaleX + dx);
            const y = Math.round(bubble.yMm * scaleY + dy);
            const radius = radiusPx;
            if (x - radius < 0 || y - radius < 0 || x + radius >= width || y + radius >= height) return null;
            const value = ringScore(x, y, radius);
            values.push(value);
            if (value >= 6) supported++;
            if (value >= 8) strong++;
            distance += Math.hypot(x / scaleX - bubble.xMm, y / scaleY - bubble.yMm);
        }
        if (supported < Math.max(2, Math.ceil(row.bubbles.length / 2))) return null;
        values.sort((a, b) => a - b);
        const trim = Math.floor(values.length * 0.2);
        let total = 0;
        for (let index = trim; index < values.length - trim; index++) total += values[index];
        const middle = Math.floor(values.length / 2);
        const middleValue = values.length % 2 === 0
            ? (values[middle - 1] + values[middle]) / 2
            : values[middle];
        const robustContrast = 0.7 * total / (values.length - 2 * trim) + 0.3 * middleValue;
        if (robustContrast < 6) return null;
        distance /= row.bubbles.length;
        if (distance > searchRadiusMm) return null;
        const score =
            Math.min(1, robustContrast / 64) * (1 - 0.6 * distance / searchRadiusMm) -
            Math.min(0.06, Math.abs(pitchScale - 1) * 0.15) -
            0.01 * Math.abs(radiusPx / (row.bubbles[0].radiusMm * radiusScale) - 1) / 0.12;
        if (score <= 0) return null;
        return {
            dx,
            dy,
            pitchScale,
            contrast: robustContrast,
            strongBubbleCount: strong,
            score,
            targetX: (anchorX + (row.xMm - anchorX) * pitchScale) * scaleX + dx,
            targetY: row.yMm * scaleY + dy,
            radiusPx,
        };
    };
    const candidateFor = (row: SnapQuestionRow, dx: number, dy: number, pitchScale: number, radii?: readonly number[]): Candidate | null => {
        let best: Candidate | null = null;
        const probes = radii ?? (candidateRadiusProbes.get(row) ?? initialRadiusProbes.get(row)!).slice(0, 1);
        for (let index = 0; index < probes.length; index++) {
            const candidate = candidateAtRadius(row, dx, dy, pitchScale, probes[index]);
            if (candidate && (!best || candidate.score > best.score || (candidate.score === best.score && candidate.contrast > best.contrast))) {
                best = candidate;
            }
        }
        return best;
    };
    const candidateCache = new Map<SnapQuestionRow, Candidate[]>();
    const candidatesFor = (row: SnapQuestionRow): Candidate[] => {
        const cached = candidateCache.get(row);
        if (cached) return cached;
        const radius = median(row.bubbles.map((bubble) => bubble.radiusMm * radiusScale));
        const nmsRadius = Math.max(2, Math.round(0.35 * radius));
        const refined: Candidate[] = [];
        const allProbes = candidateRadiusProbes.get(row) ?? initialRadiusProbes.get(row)!;
        const primaryProbes = allProbes.slice(0, 1);
        const refineScales = (scales: readonly number[], radii: readonly number[]): void => {
            if (radii.length === 0) return;
            const coarse: Candidate[] = [];
            const stepsX: number[] = [0];
            const stepsY: number[] = [0];
            for (let step = coarseStep; step <= limitX; step += coarseStep) stepsX.push(-step, step);
            for (let step = coarseStep; step <= limitY; step += coarseStep) stepsY.push(-step, step);
            for (const pitchScale of scales) {
                for (const dy of stepsY) {
                    for (const dx of stepsX) {
                        const candidate = candidateFor(row, dx, dy, pitchScale, radii);
                        if (candidate) coarse.push(candidate);
                    }
                }
            }
            coarse.sort((a, b) => b.score - a.score);
            const peaks: Candidate[] = [];
            for (const candidate of coarse) {
                if (peaks.every((other) => Math.hypot(candidate.targetX - other.targetX, candidate.targetY - other.targetY) > nmsRadius)) {
                    peaks.push(candidate);
                    if (peaks.length === 16) break;
                }
            }
            const seen = new Set<string>();
            for (const peak of peaks) {
                const left = Math.max(-limitX, Math.floor(peak.dx - coarseStep));
                const right = Math.min(limitX, Math.ceil(peak.dx + coarseStep));
                const top = Math.max(-limitY, Math.floor(peak.dy - coarseStep));
                const bottom = Math.min(limitY, Math.ceil(peak.dy + coarseStep));
                for (let dy = top; dy <= bottom; dy++) {
                    for (let dx = left; dx <= right; dx++) {
                        const key = `${peak.pitchScale}:${dx}:${dy}:${peak.radiusPx ?? 0}`;
                        if (seen.has(key)) continue;
                        seen.add(key);
                        const candidate = candidateFor(row, dx, dy, peak.pitchScale, [peak.radiusPx ?? radii[0]]);
                        if (candidate) refined.push(candidate);
                    }
                }
            }
        };
        refineScales([1], primaryProbes);
        if (!refined.some((candidate) => candidate.contrast >= 18) && allProbes.length > 1) {
            refineScales([1], allProbes.slice(1));
        }
        if (!refined.some((candidate) => candidate.contrast >= 12)) {
            refineScales([0.85, 0.92, 1.08, 1.15, 1.22], primaryProbes);
        }
        refined.sort((a, b) => b.score - a.score);
        const current = currentCandidates.get(row) ?? candidateFor(row, 0, 0, 1, primaryProbes);
        const retained: Candidate[] = current ? [current] : [];
        for (const candidate of refined) {
            if (retained.every((other) => Math.hypot(candidate.targetX - other.targetX, candidate.targetY - other.targetY) > nmsRadius)) {
                retained.push(candidate);
                if (retained.length === 24) break;
            }
        }
        retained.sort((a, b) => b.score - a.score);
        candidateCache.set(row, retained);
        return retained;
    };
    const columns: SnapQuestionRow[][] = [];
    for (const row of [...rows].sort((a, b) => a.xMm - b.xMm || a.yMm - b.yMm)) {
        const pitches = row.bubbles.slice(1).map((bubble, index) =>
            Math.hypot(bubble.xMm - row.bubbles[index].xMm, bubble.yMm - row.bubbles[index].yMm),
        );
        const anchorTolerance = Math.max(3, Math.min(12, 0.75 * (pitches.length ? median(pitches) : 6)));
        let column = columns.find((group) => {
            const first = group[0];
            if (first.bubbles.length !== row.bubbles.length) return false;
            if (!first.bubbles.every((bubble, index) => bubble.label === row.bubbles[index].label)) return false;
            const firstX = median(group.map((entry) => entry.bubbles[0].xMm));
            const lastX = median(group.map((entry) => entry.bubbles[entry.bubbles.length - 1].xMm));
            return (
                Math.abs(row.bubbles[0].xMm - firstX) <= anchorTolerance &&
                Math.abs(row.bubbles[row.bubbles.length - 1].xMm - lastX) <= anchorTolerance
            );
        });
        if (!column) {
            column = [];
            columns.push(column);
        }
        column.push(row);
    }
    const rowColumns = new Map<SnapQuestionRow, SnapQuestionRow[]>();
    const spacingByRow = new Map<SnapQuestionRow, number>();
    const exclusionByRow = new Map<SnapQuestionRow, number>();
    for (const column of columns) {
        const numbers = new Map<SnapQuestionRow, number>();
        for (const row of column) {
            const start = Number(sections[row.sectionIndex].startQ);
            if (Number.isInteger(start) && start > 0) numbers.set(row, start + row.questionIndex);
        }
        const uniqueNumbers = new Set(numbers.values());
        const hasLogicalOrder = numbers.size === column.length && uniqueNumbers.size === column.length;
        const oneSection = column.every((row) => row.sectionIndex === column[0].sectionIndex);
        column.sort((a, b) =>
            hasLogicalOrder
                ? numbers.get(a)! - numbers.get(b)!
                : oneSection
                  ? a.questionIndex - b.questionIndex
                  : a.yMm - b.yMm,
        );
        for (const row of column) rowColumns.set(row, column);
    }
    for (const column of columns) {
        for (const row of column) {
            const rowRadiusPx = median(
                row.bubbles.map((bubble) => bubble.radiusMm * radiusScale),
            );
            const minimumSpacingPx = 2.4 * rowRadiusPx;
            let spacing = (Number(sections[row.sectionIndex].rowPitchMm) || 6) * scaleY;
            for (const other of column) {
                const gap = Math.abs(other.yMm - row.yMm) * scaleY;
                if (other !== row && gap > 1e-6) spacing = Math.min(spacing, gap);
            }
            // Misplaced programmed rows must not collapse physical claim masks.
            // 2.4r is a configured 20% separation margin over the 2r diameter,
            // not a claim that real nonoverlapping circles require exactly 2.4r.
            spacing = Math.max(minimumSpacingPx, spacing);
            spacingByRow.set(row, spacing);
            const pitches = row.bubbles.slice(1).map((bubble, index) =>
                Math.hypot(
                    (bubble.xMm - row.bubbles[index].xMm) * scaleX,
                    (bubble.yMm - row.bubbles[index].yMm) * scaleY,
                ),
            );
            const pitch = pitches.length ? median(pitches) : spacing;
            const rowSafeExclusionPx = Math.min(0.75 * pitch, 0.45 * spacing);
            // A claimed circle excludes its rim as a competing circle center.
            const exclusionPx = Math.max(1.25 * rowRadiusPx, rowSafeExclusionPx);
            exclusionByRow.set(row, exclusionPx);
        }
    }
    const recordMeasuredRadius = (row: SnapQuestionRow, radius: number): void => {
        if (!Number.isFinite(radius) || radius <= 0) return;
        const key = Math.round(radius * 2) / 2;
        if (!offsetTables.has(key)) return;
        const sectionSamples = measuredBySection.get(row.sectionIndex) ?? [];
        sectionSamples.push(key);
        measuredBySection.set(row.sectionIndex, sectionSamples);
        const column = rowColumns.get(row);
        if (column) {
            const columnSamples = measuredByColumn.get(column) ?? [];
            columnSamples.push(key);
            measuredByColumn.set(column, columnSamples);
        }
    };
    const radiusProbesForRow = (row: SnapQuestionRow, selected?: number): readonly number[] => {
        const initial = initialRadiusProbes.get(row)!;
        if (options.adjustDiameter === false) return initial;
        const nominal = median(row.bubbles.map((bubble) => bubble.radiusMm * radiusScale));
        const probes: number[] = [];
        const column = rowColumns.get(row);
        const columnSamples = column ? measuredByColumn.get(column) : undefined;
        const sectionSamples = measuredBySection.get(row.sectionIndex);
        const preferred: number[] = [];
        if (columnSamples?.length) preferred.push(median(columnSamples));
        if (sectionSamples?.length) preferred.push(median(sectionSamples));
        if (selected !== undefined) preferred.push(selected);
        for (const radius of preferred) {
            const key = Math.round(radius * 2) / 2;
            if (key < nominal * 0.75 || key > nominal * 1.25 || !offsetTables.has(key)) continue;
            if (!probes.includes(key)) probes.push(key);
        }
        for (const radius of initial) if (!probes.includes(radius)) probes.push(radius);
        return probes;
    };
    const snappedBySection = new Map<number, { points: SnappedOptionPoint[]; diameters: number[]; snappedRows: number }>();
    for (const row of rows) {
        let output = snappedBySection.get(row.sectionIndex);
        if (!output) {
            output = { points: [], diameters: [], snappedRows: 0 };
            snappedBySection.set(row.sectionIndex, output);
        }
        for (const bubble of row.bubbles) {
            output.points[row.questionIndex * row.bubbles.length + bubble.optionIndex] = {
                label: bubble.label,
                x_mm: bubble.xMm,
                y_mm: bubble.yMm,
            };
        }
    }
    type Claim = { xPx: number; yPx: number; exclusionPx: number; row: SnapQuestionRow };
    const claimedCircles: Claim[] = [];
    const resolvedTargets = new Map<SnapQuestionRow, Candidate>();
    const plannedTargets = new Map<SnapQuestionRow, Candidate>();
    const candidateIsUnclaimed = (row: SnapQuestionRow, candidate: Candidate): boolean => {
        const anchorX = row.bubbles[0].xMm;
        for (const bubble of row.bubbles) {
            const x = (anchorX + (bubble.xMm - anchorX) * candidate.pitchScale) * scaleX + candidate.dx;
            const y = bubble.yMm * scaleY + candidate.dy;
            for (const claim of claimedCircles) {
                if (claim.row !== row && Math.hypot(x - claim.xPx, y - claim.yPx) <= claim.exclusionPx) return false;
            }
        }
        const column = rowColumns.get(row) ?? [];
        const rowIndex = column.indexOf(row);
        for (let index = 0; index < column.length; index++) {
            const other = column[index];
            if (other === row) continue;
            const target = resolvedTargets.get(other) ?? plannedTargets.get(other);
            if (!target) continue;
            const gap = Math.max(2, 0.25 * Math.min(spacingByRow.get(row) ?? 1, spacingByRow.get(other) ?? 1));
            if (index < rowIndex && target.targetY >= candidate.targetY - gap) return false;
            if (index > rowIndex && candidate.targetY >= target.targetY - gap) return false;
            const exclusion = Math.max(exclusionByRow.get(row) ?? 0, exclusionByRow.get(other) ?? 0);
            if (Math.hypot(target.targetX - candidate.targetX, target.targetY - candidate.targetY) <= exclusion) return false;
        }
        return true;
    };
    const pointIsFreeForRow = (row: SnapQuestionRow, px: number, py: number): boolean => {
        for (const claim of claimedCircles) {
            if (claim.row !== row && Math.hypot(px - claim.xPx, py - claim.yPx) <= claim.exclusionPx) {
                return false;
            }
        }
        for (const [otherRow, planned] of plannedTargets.entries()) {
            if (otherRow === row || otherRow.matched) continue;
            const otherExcl = exclusionByRow.get(otherRow) ?? 0;
            const otherAnchorX = otherRow.bubbles[0]?.xMm ?? otherRow.xMm;
            for (const otherBubble of otherRow.bubbles) {
                const ox = (otherAnchorX + (otherBubble.xMm - otherAnchorX) * planned.pitchScale) * scaleX + planned.dx;
                const oy = otherBubble.yMm * scaleY + planned.dy;
                if (Math.hypot(px - ox, py - oy) <= otherExcl) {
                    return false;
                }
            }
        }
        return true;
    };
    type CurrentAnchorState = {
        count: number;
        score: number;
        row: SnapQuestionRow | null;
        candidate: Candidate | null;
        previous: CurrentAnchorState | null;
    };
    for (const column of columns) {
        const states: CurrentAnchorState[] = [{ count: 0, score: 0, row: null, candidate: null, previous: null }];
        for (const row of column) {
            const candidate = currentCandidates.get(row);
            if (!candidate) continue;
            let predecessor: CurrentAnchorState | null = null;
            for (const state of states) {
                if (state.row && state.candidate) {
                    const gap = Math.max(2, 0.25 * Math.min(spacingByRow.get(row) ?? 1, spacingByRow.get(state.row) ?? 1));
                    if (state.candidate.targetY >= candidate.targetY - gap) continue;
                    const exclusion = Math.max(exclusionByRow.get(row) ?? 0, exclusionByRow.get(state.row) ?? 0);
                    if (Math.hypot(candidate.targetX - state.candidate.targetX, candidate.targetY - state.candidate.targetY) <= exclusion) continue;
                }
                if (
                    !predecessor || state.count > predecessor.count ||
                    (state.count === predecessor.count && state.score > predecessor.score)
                ) predecessor = state;
            }
            if (predecessor) states.push({
                count: predecessor.count + 1,
                score: predecessor.score + candidate.score,
                row,
                candidate,
                previous: predecessor,
            });
        }
        let best = states[0];
        for (const state of states) {
            if (state.count > best.count || (state.count === best.count && state.score > best.score)) best = state;
        }
        const selected = new Set<SnapQuestionRow>();
        for (let state: CurrentAnchorState | null = best; state; state = state.previous) {
            if (state.row) selected.add(state.row);
        }
        for (const row of column) {
            if (!selected.has(row)) continue;
            const candidate = currentCandidates.get(row);
            if (!candidate || !candidateIsUnclaimed(row, candidate)) continue;
            if (!row.bubbles.every((bubble) => pointIsFreeForRow(row, bubble.xMm * scaleX, bubble.yMm * scaleY))) continue;
            const output = snappedBySection.get(row.sectionIndex);
            if (!output) continue;
            for (const bubble of row.bubbles) {
                claimedCircles.push({
                    xPx: bubble.xMm * scaleX,
                    yPx: bubble.yMm * scaleY,
                    exclusionPx: exclusionByRow.get(row) ?? 0,
                    row,
                });
            }
            const measured = candidate.radiusPx;
            if (measured !== undefined) {
                recordMeasuredRadius(row, measured);
                if (options.adjustDiameter !== false) output.diameters.push((measured * 2) / radiusScale);
            }
            output.snappedRows++;
            row.coarseDx = 0;
            row.coarseDy = 0;
            row.matched = true;
            resolvedTargets.set(row, candidate);
        }
    }
    const refineAndClaimRow = (row: SnapQuestionRow, candidate: Candidate): boolean => {
        if (!candidateIsUnclaimed(row, candidate)) return false;
        const anchorX = row.bubbles[0].xMm;
        const ringScore = row.bubbles.length === 2 ? currentBoundaryContrast : contrast;
        const refinementRadii = radiusProbesForRow(row, candidate.radiusPx);
        candidateRadiusProbes.set(row, refinementRadii);
        const adjustments = row.bubbles.map((bubble) => {
            const baseX = (anchorX + (bubble.xMm - anchorX) * candidate.pitchScale) * scaleX + candidate.dx;
            const baseY = bubble.yMm * scaleY + candidate.dy;
            const radius = bubble.radiusMm * radiusScale;
            const shiftLimit = Math.max(4, Math.round(radius * 0.85));
            let best = { score: -Infinity, contrast: 0, dx: 0, dy: 0, radius };
            for (let dy = -shiftLimit; dy <= shiftLimit; dy++) {
                for (let dx = -shiftLimit; dx <= shiftLimit; dx++) {
                    const x = baseX + dx;
                    const y = baseY + dy;
                    if (!pointIsFreeForRow(row, x, y)) continue;
                    for (const testedRadius of refinementRadii) {
                        const value = ringScore(x, y, testedRadius);
                        const score = value - 0.02 * (dx * dx + dy * dy);
                        if (score > best.score) best = { score, contrast: value, dx, dy, radius: testedRadius };
                    }
                }
            }
            const originDx = best.dx;
            const originDy = best.dy;
            const bestRadius = best.radius;
            for (let stepY = -4; stepY <= 4; stepY++) {
                for (let stepX = -4; stepX <= 4; stepX++) {
                    const dx = originDx + stepX * 0.2;
                    const dy = originDy + stepY * 0.2;
                    const x = baseX + dx;
                    const y = baseY + dy;
                    if (!pointIsFreeForRow(row, x, y)) continue;
                    const value = ringScore(x, y, bestRadius);
                    const score = value - 0.02 * (dx * dx + dy * dy);
                    if (score > best.score) best = { score, contrast: value, dx, dy, radius: bestRadius };
                }
            }
            if (best.contrast >= 8) {
                const fitted = fitPrintedRingCenter(baseX + best.dx, baseY + best.dy, best.radius);
                if (fitted && pointIsFreeForRow(row, fitted.x, fitted.y)) {
                    const fittedRadius = options.adjustDiameter === false ? best.radius : fitted.radius;
                    const value = ringScore(fitted.x, fitted.y, fittedRadius);
                    if (value >= 8) {
                        best = { ...best, dx: fitted.x - baseX, dy: fitted.y - baseY, contrast: value, radius: fittedRadius };
                    }
                }
            }
            return { bubble, baseX, baseY, ...best };
        });
        const confident = adjustments.filter((entry) => entry.contrast >= 8);
        if (confident.length < Math.max(2, Math.ceil(row.bubbles.length / 2))) return false;
        const verifiedRadii = confident.map((entry) => entry.radius);
        const medianDx = median(confident.map((entry) => entry.dx));
        const centers = confident.map((entry) => ({ x: entry.baseX + entry.dx, y: entry.baseY + entry.dy }));
        const slopes: number[] = [];
        for (let left = 0; left < centers.length; left++) {
            for (let right = left + 1; right < centers.length; right++) {
                const dx = centers[right].x - centers[left].x;
                if (Math.abs(dx) < 2) continue;
                const slope = (centers[right].y - centers[left].y) / dx;
                if (Math.abs(slope) <= 0.2679491924311227) slopes.push(slope);
            }
        }
        const sourceSpanX = (row.bubbles[row.bubbles.length - 1].xMm - row.bubbles[0].xMm) * scaleX;
        const sourceSpanY = (row.bubbles[row.bubbles.length - 1].yMm - row.bubbles[0].yMm) * scaleY;
        const sourceSlope = Math.abs(sourceSpanX) >= 2 ? sourceSpanY / sourceSpanX : 0;
        const slope = slopes.length ? median(slopes) : Math.max(-0.2679491924311227, Math.min(0.2679491924311227, sourceSlope));
        const intercept = median(centers.map((center) => center.y - slope * center.x));
        const residualLimit = Math.max(2, Math.min(6, 0.35 * median(row.bubbles.map((bubble) => bubble.radiusMm * radiusScale))));
        const lineScale = Math.sqrt(1 + slope * slope);
        const points: SnappedOptionPoint[] = [];
        const diameters: number[] = [];
        for (const entry of adjustments) {
            const fittedX = entry.baseX + entry.dx;
            const fittedY = entry.baseY + entry.dy;
            const residual = Math.abs(fittedY - (slope * fittedX + intercept)) / lineScale;
            const inherit = entry.contrast < 8 || residual > residualLimit;
            const px = inherit ? entry.baseX + medianDx : fittedX;
            const py = inherit ? slope * px + intercept : fittedY;
            const point = {
                label: entry.bubble.label,
                x_mm: Number((px / scaleX).toFixed(3)),
                y_mm: Number((py / scaleY).toFixed(3)),
            };
            const finalX = point.x_mm * scaleX;
            const finalY = point.y_mm * scaleY;
            const radius = entry.bubble.radiusMm * radiusScale;
            if (
                finalX - radius < 0 || finalY - radius < 0 ||
                finalX + radius >= width || finalY + radius >= height ||
                !pointIsFreeForRow(row, finalX, finalY)
            ) return false;
            points.push(point);
            if (!inherit && options.adjustDiameter !== false) diameters.push((entry.radius * 2) / radiusScale);
        }
        const finalTarget = {
            ...candidate,
            targetX: median(points.map((point) => point.x_mm * scaleX)),
            targetY: median(points.map((point) => point.y_mm * scaleY)),
        };
        if (!candidateIsUnclaimed(row, finalTarget)) return false;
        const output = snappedBySection.get(row.sectionIndex);
        if (!output) return false;
        for (let index = 0; index < points.length; index++) {
            const point = points[index];
            output.points[row.questionIndex * row.bubbles.length + index] = point;
            claimedCircles.push({
                xPx: point.x_mm * scaleX,
                yPx: point.y_mm * scaleY,
                exclusionPx: exclusionByRow.get(row) ?? 0,
                row,
            });
        }
        output.diameters.push(...diameters);
        recordMeasuredRadius(row, median(verifiedRadii));
        changedSectionIndices.add(row.sectionIndex);
        output.snappedRows++;
        row.coarseDx = candidate.dx;
        row.coarseDy = candidate.dy;
        row.matched = true;
        resolvedTargets.set(row, finalTarget);
        plannedTargets.delete(row);
        return true;
    };

    type ColumnState = {
        score: number;
        row: SnapQuestionRow | null;
        candidate: Candidate | null;
        previous: ColumnState | null;
    };
    for (const column of columns) {
        for (const row of column) {
            if (row.matched) continue;
            candidateRadiusProbes.set(row, radiusProbesForRow(row));
            candidatesFor(row);
        }
        const gaps: number[] = [];
        for (let index = 1; index < column.length; index++) {
            const gap = (column[index].yMm - column[index - 1].yMm) * scaleY;
            if (gap > 0) gaps.push(gap);
        }
        const rowSpacingPx = gaps.length ? median(gaps) : spacingByRow.get(column[0]) ?? 1;
        const orderGap = Math.max(2, 0.25 * rowSpacingPx);
        let states: ColumnState[] = [{ score: 0, row: null, candidate: null, previous: null }];
        for (const row of column) {
            if (row.matched) continue;
            const nextStates = states.slice();
            for (const candidate of candidateCache.get(row) ?? []) {
                if (!candidateIsUnclaimed(row, candidate)) continue;
                let predecessor: ColumnState | null = null;
                let bestScore = -Infinity;
                for (const state of states) {
                    let penalty = 0;
                    if (state.row && state.candidate) {
                        const previous = state.candidate;
                        if (previous.targetY >= candidate.targetY - orderGap) continue;
                        const exclusion = Math.max(exclusionByRow.get(state.row) ?? 0, exclusionByRow.get(row) ?? 0);
                        if (Math.hypot(candidate.targetX - previous.targetX, candidate.targetY - previous.targetY) <= exclusion) continue;
                        const expected = (row.yMm - state.row.yMm) * scaleY;
                        penalty = 0.08 * Math.abs((candidate.targetY - previous.targetY) - expected) / Math.max(1, rowSpacingPx);
                    }
                    const score = state.score + candidate.score - penalty;
                    if (score > bestScore) {
                        bestScore = score;
                        predecessor = state;
                    }
                }
                if (predecessor && bestScore > 0) nextStates.push({ score: bestScore, row, candidate, previous: predecessor });
            }
            states = nextStates;
        }
        let bestState = states[0];
        for (const state of states) if (state.score > bestState.score) bestState = state;
        for (let state: ColumnState | null = bestState; state; state = state.previous) {
            if (state.row && state.candidate) plannedTargets.set(state.row, state.candidate);
        }
        for (const row of column) {
            if (row.matched) continue;
            const selected = plannedTargets.get(row);
            if (selected && refineAndClaimRow(row, selected)) continue;
            let claimed = false;
            for (const candidate of candidateCache.get(row) ?? []) {
                if (candidate === selected || !candidateIsUnclaimed(row, candidate)) continue;
                if (refineAndClaimRow(row, candidate)) {
                    claimed = true;
                    break;
                }
            }
            if (!claimed) plannedTargets.delete(row);
        }
        for (const row of column) {
            if (row.matched) continue;
            for (const candidate of candidateCache.get(row) ?? []) {
                if (!candidateIsUnclaimed(row, candidate)) continue;
                if (refineAndClaimRow(row, candidate)) break;
            }
        }
    }
    contrastCache.clear();

    let snappedQuestionsCount = 0;
    let snappedBubblesCount = 0;
    for (const output of snappedBySection.values()) {
        snappedQuestionsCount += output.snappedRows;
    }
    for (const row of rows) {
        if (row.matched) snappedBubblesCount += row.bubbles.length;
    }
    const updatedSections = sections.map((section, sectionIndex) => {
        if (!changedSectionIndices.has(sectionIndex)) return { ...section };
        const snapped = snappedBySection.get(sectionIndex);
        if (!snapped || snapped.snappedRows === 0) return { ...section };
        const bubbleDiameterMm =
            options.adjustDiameter !== false && snapped.diameters.length > 0
                ? Math.max(options.minBubbleMm ?? MIN_BUBBLE_MM, median(snapped.diameters))
                : Number(section.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
        const measuredPitches: number[] = [];
        const questionCount = Math.max(1, Number(section.questionsCount) || 1);
        const opts = Math.max(1, Math.round(snapped.points.length / questionCount));
        if (snapped.points.length === questionCount * opts) {
            for (let row = 0; row < questionCount; row++) {
                for (let option = 0; option < opts - 1; option++) {
                    const current = snapped.points[row * opts + option];
                    const next = snapped.points[row * opts + option + 1];
                    measuredPitches.push(Math.hypot(next.x_mm - current.x_mm, next.y_mm - current.y_mm));
                }
            }
        }
        const bubblePitchMm = measuredPitches.length > 0
            ? Number((measuredPitches.reduce((sum, value) => sum + value, 0) / measuredPitches.length).toFixed(2))
            : Number(section.bubblePitchMm) || OMR_BUBBLE_PITCH_MM;
        const radius = bubbleDiameterMm / 2;
        const xs = snapped.points.map((point) => point.x_mm);
        const ys = snapped.points.map((point) => point.y_mm);
        const minX = Math.min(...xs) - radius;
        const minY = Math.min(...ys) - radius;
        const maxX = Math.max(...xs) + radius;
        const maxY = Math.max(...ys) + radius;
        return {
            ...section,
            optionPointsMm: snapped.points,
            posX: Number(minX.toFixed(3)),
            posY: Number(minY.toFixed(3)),
            w_mm: Number((maxX - minX).toFixed(3)),
            h_mm: Number((maxY - minY).toFixed(3)),
            bubbleDiameterMm: Number(bubbleDiameterMm.toFixed(3)),
            bubblePitchMm,
        };
    });
    return { sections: updatedSections, snappedQuestionsCount, snappedBubblesCount };
}
export function rescaleSectionOptionPointsPitch<
    T extends Record<string, unknown>,
>(section: T, newPitchMm: number): T {
    const optionPointsMm: Array<{ label?: string; x_mm: number; y_mm: number }> | undefined =
        Array.isArray(section.optionPointsMm)
            ? section.optionPointsMm.map((point: { label?: string; x_mm: number; y_mm: number }) => ({ ...point }))
            : undefined;
    const updatedSection = {
        ...section,
        bubblePitchMm: Number(Number(newPitchMm).toFixed(2)),
        optionPointsMm,
    };
    if (!Array.isArray(optionPointsMm) || optionPointsMm.length < 2) {
        return updatedSection;
    }

    const questionCount = Math.max(1, Number(section.questionsCount) || 1);
    const opts = Math.max(1, Math.round(optionPointsMm.length / questionCount));
    if (opts >= 2 && optionPointsMm.length === questionCount * opts) {
        for (let row = 0; row < questionCount; row++) {
            const start = row * opts;
            const rowPts = optionPointsMm.slice(start, start + opts);
            const distances = rowPts.slice(1).map((point, index) =>
                Math.hypot(
                    point.x_mm - rowPts[index].x_mm,
                    point.y_mm - rowPts[index].y_mm,
                ),
            );
            const currentRowPitch =
                distances.reduce((sum: number, distance: number) => sum + distance, 0) /
                distances.length;
            if (currentRowPitch > 0.1) {
                const scale = newPitchMm / currentRowPitch;
                for (let option = 1; option < opts; option++) {
                    optionPointsMm[start + option] = {
                        ...rowPts[option],
                        x_mm: Number(
                            (
                                rowPts[0].x_mm +
                                (rowPts[option].x_mm - rowPts[0].x_mm) * scale
                            ).toFixed(3),
                        ),
                        y_mm: Number(
                            (
                                rowPts[0].y_mm +
                                (rowPts[option].y_mm - rowPts[0].y_mm) * scale
                            ).toFixed(3),
                        ),
                    };
                }
            }
        }
    }

    return sectionBoundsFromOptionPoints(updatedSection, optionPointsMm);
}

export function resetSectionOptionPointsToUniformGrid<
    T extends Record<string, unknown>,
>(section: T): T {
    if (!Array.isArray(section.optionPointsMm) || section.optionPointsMm.length < 2) {
        return { ...section };
    }
    const optionPointsMm: Array<{ label?: string; x_mm: number; y_mm: number }> =
        section.optionPointsMm;
    const questionCount = Math.max(1, Number(section.questionsCount) || 1);
    const opts = Math.max(1, Math.round(optionPointsMm.length / questionCount));
    if (opts < 2 || optionPointsMm.length !== questionCount * opts) {
        return { ...section };
    }
    const diameter = Number(section.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
    const pitch = Math.max(
        Number(section.bubblePitchMm) || OMR_BUBBLE_PITCH_MM,
        minimumRecognitionPitchMm(diameter),
    );
    const rotRad = ((Number(section.rotationDeg) || 0) * Math.PI) / 180;
    for (let row = 0; row < questionCount; row++) {
        const start = row * opts;
        const rowPts = optionPointsMm.slice(start, start + opts);
        const p0 = rowPts[0];
        const last = rowPts[opts - 1];
        const dx = last.x_mm - p0.x_mm;
        const dy = last.y_mm - p0.y_mm;
        const length = Math.hypot(dx, dy);
        const ux = length > 0.1 ? dx / length : Math.cos(rotRad);
        const uy = length > 0.1 ? dy / length : Math.sin(rotRad);
        for (let option = 0; option < opts; option++) {
            optionPointsMm[start + option] = {
                label: rowPts[option].label,
                x_mm: Number((p0.x_mm + option * pitch * ux).toFixed(3)),
                y_mm: Number((p0.y_mm + option * pitch * uy).toFixed(3)),
            };
        }
    }
    return sectionBoundsFromOptionPoints(
        { ...section, optionPointsMm, bubblePitchMm: Number(pitch.toFixed(2)) },
        optionPointsMm,
    );
}

function sectionBoundsFromOptionPoints<T extends Record<string, unknown>>(
    section: T,
    optionPointsMm: Array<{ x_mm: number; y_mm: number }>,
): T {
    const radius = (Number(section.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM) / 2;
    const xs = optionPointsMm.map((point) => point.x_mm);
    const ys = optionPointsMm.map((point) => point.y_mm);
    const minX = Math.min(...xs) - radius;
    const minY = Math.min(...ys) - radius;
    const maxX = Math.max(...xs) + radius;
    const maxY = Math.max(...ys) + radius;
    return {
        ...section,
        optionPointsMm,
        posX: Number(minX.toFixed(3)),
        posY: Number(minY.toFixed(3)),
        w_mm: Number((maxX - minX).toFixed(3)),
        h_mm: Number((maxY - minY).toFixed(3)),
    };
}


/**
 * Emit native fieldBlocks mapping for OMRChecker validation and compilation.
 */
export function emitNativeMapping(
    pageWidthMm: number,
    pageHeightMm: number,
    blocks: StudioBlock[],
    pageCount?: number,
    options?: { minBubbleMm?: number },
): NativeMappingPayload {
    const nativeBlocks: NativeMappingBlock[] = [];

    for (const block of blocks) {
        if (['mcq', 'tf', 'likert', 'custom'].includes(block.type)) {
            const qs = Math.max(1, block.questions ?? 10);
            const cols = Math.max(1, block.cols ?? 1);
            const optionLabels =
                block.bubble_values ??
                block.optionLabels ??
                block.custom_values;
            let opts = 4;
            if (Array.isArray(optionLabels) && optionLabels.length >= 2) {
                opts = optionLabels.length;
            } else if (block.type === 'tf') {
                opts = 2;
            } else if (block.type === 'likert') {
                opts = 5;
            } else if (
                Number.isFinite(Number(block.options)) &&
                Number(block.options) >= 2
            ) {
                opts = Number(block.options);
            }

            const start = Math.max(1, block.start_q ?? 1);
            const rowsPerCol = Math.ceil(qs / cols);
            const geometry = effectiveRecognitionGeometryMm({
                kind: 'grid',
                bubbleDiameterMm: block.bubble_diameter_mm,
                bubblePitchMm: block.bubble_pitch_mm,
                rowPitchMm: block.row_pitch_mm,
                minBubbleMm: options?.minBubbleMm,
            });
            const bubbleSizePx = mmToPx(geometry.bubbleDiameterMm);
            const hasCustomOptionPitch =
                Number.isFinite(Number(block.bubble_pitch_mm)) &&
                Number(block.bubble_pitch_mm) > 0;
            const hasCustomRowPitch =
                Number.isFinite(Number(block.row_pitch_mm)) &&
                Number(block.row_pitch_mm) > 0;
            const customOptionPitch = hasCustomOptionPitch
                ? mmToPx(geometry.bubblePitchMm)
                : null;
            const customRowPitch = hasCustomRowPitch
                ? mmToPx(geometry.rowPitchMm)
                : null;
            if (cols === 1) {
                const labels = Array.from({ length: qs }, (_, i) =>
                    block.area_code
                        ? `${block.area_code}_Q_${start + i}`
                        : `q${start + i}`,
                );
                const rawBlockW = mmToPx(block.w_mm);
                const rawBlockH = mmToPx(block.h_mm);
                const optionPitch =
                    customOptionPitch ??
                    Math.max(bubbleSizePx + 15, Math.round(rawBlockW / opts));
                const rowPitch =
                    customRowPitch ??
                    Math.max(
                        bubbleSizePx + 15,
                        Math.round(rawBlockH / rowsPerCol),
                    );
                const explicitPositions =
                    Array.isArray(block.option_points_mm) &&
                    block.option_points_mm.length === qs * opts
                        ? Array.from({ length: qs }, (_, rowIndex) =>
                              block.option_points_mm
                                  .slice(rowIndex * opts, (rowIndex + 1) * opts)
                                  .map((point) => [
                                      mmToPx(point.x_mm) -
                                          Math.floor(bubbleSizePx / 2),
                                      mmToPx(point.y_mm) -
                                          Math.floor(bubbleSizePx / 2),
                                  ]),
                          )
                        : undefined;
                const explicitPositionsFlat = explicitPositions?.flat() ?? [];
                const explicitXs = explicitPositionsFlat.map(
                    (point) => point[0],
                );
                const explicitYs = explicitPositionsFlat.map(
                    (point) => point[1],
                );
                const explicitWidth =
                    explicitPositions && explicitXs.length > 0
                        ? Math.max(...explicitXs) -
                          Math.min(...explicitXs) +
                          bubbleSizePx
                        : null;
                const explicitHeight =
                    explicitPositions && explicitYs.length > 0
                        ? Math.max(...explicitYs) -
                          Math.min(...explicitYs) +
                          bubbleSizePx
                        : null;
                const requiredW = bubbleSizePx + (opts - 1) * optionPitch;
                const requiredH =
                    bubbleSizePx + (rowsPerCol - 1) * rowPitch;
                const blockW =
                    rowsPerCol === 1
                        ? requiredW
                        : Math.max(rawBlockW, requiredW);
                const blockH =
                    rowsPerCol === 1
                        ? requiredH
                        : Math.max(rawBlockH, requiredH);

                nativeBlocks.push({
                    name: block.id,
                    page_no: block.page_no ?? 1,
                    field_type: opts === 5 ? 'QTYPE_MCQ5' : 'QTYPE_MCQ4',
                    label_prefix: 'q',
                    labels,
                    ...(block.subtest_code || block.area_code
                        ? {
                              subtest_code:
                                  block.subtest_code || block.area_code,
                          }
                        : {}),
                    ...(block.test_instrument_subtest_id != null
                        ? {
                              test_instrument_subtest_id:
                                  block.test_instrument_subtest_id,
                          }
                        : {}),
                    rows: rowsPerCol,
                    options: optionLabels?.length ?? opts,
                    ...(optionLabels && optionLabels.length > 0
                        ? { bubble_values: optionLabels }
                        : {}),
                    origin_x:
                        explicitPositions && explicitXs.length > 0
                            ? Math.min(...explicitXs)
                            : mmToPx(
                                  block.x_mm +
                                      recognitionOriginOffsetMm(block).x,
                              ),
                    origin_y:
                        explicitPositions && explicitYs.length > 0
                            ? Math.min(...explicitYs)
                            : mmToPx(
                                  block.y_mm +
                                      recognitionOriginOffsetMm(block).y,
                              ),
                    width: explicitWidth ?? blockW,
                    height: explicitHeight ?? blockH,
                    option_pitch_px: optionPitch,
                    row_pitch_px: rowPitch,
                    bubble_width_px: bubbleSizePx,
                    bubble_height_px: bubbleSizePx,
                    ...(explicitPositions
                        ? { bubble_positions: explicitPositions }
                        : {}),
                });
            } else {
                // Multi-column MCQ: emit distinct sub-blocks for OMRChecker
                const colWidthMm = block.w_mm / cols;
                const explicitPositions =
                    Array.isArray(block.option_points_mm) &&
                    block.option_points_mm.length === qs * opts
                        ? Array.from({ length: qs }, (_, rowIndex) =>
                              block.option_points_mm
                                  .slice(rowIndex * opts, (rowIndex + 1) * opts)
                                  .map((point) => [
                                      mmToPx(point.x_mm) -
                                          Math.floor(bubbleSizePx / 2),
                                      mmToPx(point.y_mm) -
                                          Math.floor(bubbleSizePx / 2),
                                  ]),
                          )
                        : undefined;
                for (let c = 0; c < cols; c++) {
                    const colStart = start + c * rowsPerCol;
                    const colCount = Math.min(rowsPerCol, qs - c * rowsPerCol);
                    if (colCount <= 0) break;

                    const labels = Array.from({ length: colCount }, (_, i) =>
                        block.area_code
                            ? `${block.area_code}_Q_${colStart + i}`
                            : `q${colStart + i}`,
                    );
                    const colX = block.x_mm + c * colWidthMm;
                    const blockW = mmToPx(colWidthMm);
                    const blockH = mmToPx(block.h_mm);
                    const optionPitch =
                        customOptionPitch ??
                        Math.max(bubbleSizePx + 15, Math.round(blockW / opts));
                    const rowPitch =
                        customRowPitch ??
                        Math.max(
                            bubbleSizePx + 15,
                            Math.round(blockH / rowsPerCol),
                        );

                    nativeBlocks.push({
                        name: `${block.id}_col${c + 1}`,
                        page_no: block.page_no ?? 1,
                        field_type: opts === 5 ? 'QTYPE_MCQ5' : 'QTYPE_MCQ4',
                        label_prefix: 'q',
                        labels,
                        rows: colCount,
                        options: optionLabels?.length ?? opts,
                        ...(block.subtest_code || block.area_code
                            ? {
                                  subtest_code:
                                      block.subtest_code || block.area_code,
                              }
                            : {}),
                        ...(block.test_instrument_subtest_id != null
                            ? {
                                  test_instrument_subtest_id:
                                      block.test_instrument_subtest_id,
                              }
                            : {}),
                        ...(optionLabels && optionLabels.length > 0
                            ? { bubble_values: optionLabels }
                            : {}),
                        origin_x: mmToPx(
                            colX + recognitionOriginOffsetMm(block).x,
                        ),
                        origin_y: mmToPx(
                            block.y_mm + recognitionOriginOffsetMm(block).y,
                        ),
                        width: blockW,
                        height: blockH,
                        option_pitch_px: optionPitch,
                        row_pitch_px: rowPitch,
                        bubble_width_px: bubbleSizePx,
                        bubble_height_px: bubbleSizePx,
                        ...(explicitPositions
                            ? {
                                  bubble_positions: explicitPositions.slice(
                                      c * rowsPerCol,
                                      c * rowsPerCol + colCount,
                                  ),
                              }
                            : {}),
                    });
                }
            }
        } else if (block.type === 'roll') {
            const digits = Math.max(2, Math.min(10, block.digits ?? 7));
            const geometry = effectiveRecognitionGeometryMm({
                kind: 'roll',
                bubbleDiameterMm: block.bubble_diameter_mm,
                bubblePitchMm: block.bubble_pitch_mm,
                rowPitchMm: block.row_pitch_mm,
                minBubbleMm: options?.minBubbleMm,
            });
            const bubbleSizePx = mmToPx(geometry.bubbleDiameterMm);
            const labels = Array.from(
                { length: digits },
                (_, i) => `${block.id.toLowerCase()}_${i + 1}`,
            );
            const minimumPitchPx = mmToPx(
                minimumRecognitionPitchMm(
                    geometry.bubbleDiameterMm,
                    options?.minBubbleMm,
                ),
            );
            const hasCustomOptionPitch =
                Number.isFinite(Number(block.bubble_pitch_mm)) &&
                Number(block.bubble_pitch_mm) > 0;
            const hasCustomRowPitch =
                Number.isFinite(Number(block.row_pitch_mm)) &&
                Number(block.row_pitch_mm) > 0;
            const colPitch = hasCustomOptionPitch
                ? mmToPx(geometry.bubblePitchMm)
                : Math.max(
                      minimumPitchPx,
                      Math.round(mmToPx(block.w_mm) / digits),
                  );
            const rowPitch = hasCustomRowPitch
                ? mmToPx(geometry.rowPitchMm)
                : Math.max(minimumPitchPx, Math.round(mmToPx(block.h_mm) / 10));
            const blockW = Math.max(
                mmToPx(block.w_mm),
                bubbleSizePx + (digits - 1) * colPitch + 15,
            );
            const blockH = Math.max(
                mmToPx(block.h_mm),
                bubbleSizePx + (10 - 1) * rowPitch + 15,
            );
            const originX = mmToPx(
                block.x_mm + recognitionOriginOffsetMm(block).x,
            );
            const originY = mmToPx(
                block.y_mm + recognitionOriginOffsetMm(block).y,
            );
            const tiltedIdentityGrid =
                block.identity_grid === true &&
                block.roll_direction !== 'horizontal' &&
                Number(block.rotation_deg ?? 0) !== 0;
            const bubblePositions = tiltedIdentityGrid
                ? verticalRollBubblePositionsPx({
                      originX,
                      originY,
                      digitCount: digits,
                      digitPitchPx: colPitch,
                      valuePitchPx: rowPitch,
                      bubbleWidthPx: bubbleSizePx,
                      rotationDeg: Number(block.rotation_deg ?? 0),
                  })
                : undefined;
            const bubbleXs =
                bubblePositions?.flatMap((row) =>
                    row.map(([x]) => x),
                ) ?? [];
            const bubbleYs =
                bubblePositions?.flatMap((row) =>
                    row.map(([, y]) => y),
                ) ?? [];
            const positionedWidth =
                bubblePositions && bubbleXs.length > 0
                    ? Math.max(...bubbleXs) -
                      Math.min(...bubbleXs) +
                      bubbleSizePx
                    : blockW;
            const positionedHeight =
                bubblePositions && bubbleYs.length > 0
                    ? Math.max(...bubbleYs) -
                      Math.min(...bubbleYs) +
                      bubbleSizePx
                    : blockH;

            nativeBlocks.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                field_type: 'QTYPE_INT',
                label_prefix: `${block.id.toLowerCase()}_`,
                labels,
                rows: 10,
                options: 10,
                direction: 'vertical',
                origin_x: originX,
                origin_y: originY,
                width: positionedWidth,
                height: positionedHeight,
                option_pitch_px: rowPitch,
                row_pitch_px: colPitch,
                bubble_width_px: bubbleSizePx,
                bubble_height_px: bubbleSizePx,
                ...(bubblePositions
                    ? { bubble_positions: bubblePositions }
                    : {}),
            });
        }
    }

    return {
        page_width: mmToPx(pageWidthMm),
        page_height: mmToPx(pageHeightMm),
        page_count: Math.max(
            pageCount ?? 1,
            ...blocks.map((block) => block.page_no ?? 1),
        ),
        blocks: nativeBlocks,
    };
}

/**
 * Emit fixed overlay boxes (QR code, text labels, signature areas, metadata boxes)
 */
export function emitOverlayBoxes(
    blocks: StudioBlock[],
    _pageWidthMm: number,
    _pageHeightMm: number,
): { boxes: OverlayBox[] } {
    const boxes: OverlayBox[] = [];

    for (const block of blocks) {
        if (block.type === 'qr') {
            boxes.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                x: mmToPx(block.x_mm),
                y: mmToPx(block.y_mm),
                width: mmToPx(block.w_mm),
                height: mmToPx(block.h_mm),
                tag: block.tag ?? 'blank_instance_qr',
                scope: block.scope ?? 'blank_instance',
                overflow: 'truncate',
                content: null,
            });
        } else if (
            block.type === 'header' ||
            (block.type === 'text' && block.tag === 'header_title')
        ) {
            boxes.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                x: mmToPx(block.x_mm),
                y: mmToPx(block.y_mm),
                width: mmToPx(block.w_mm),
                height: mmToPx(block.h_mm),
                tag: 'header_title',
                scope: 'template',
                overflow: block.overflow ?? 'shrink',
                font_size: block.font_size ?? 14.0,
                content:
                    block.content ?? block.title ?? 'EXAMINATION ANSWER SHEET',
            });
        } else if (
            block.type === 'instructions' ||
            block.tag === 'instructions_box' ||
            block.tag === 'instruction_box'
        ) {
            boxes.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                x: mmToPx(block.x_mm),
                y: mmToPx(block.y_mm),
                width: mmToPx(block.w_mm),
                height: mmToPx(block.h_mm),
                tag: 'instructions_box',
                scope: 'template',
                overflow: block.overflow ?? 'wrap',
                font_size: block.font_size ?? 10.0,
                content:
                    block.content ??
                    block.title ??
                    'INSTRUCTIONS: USE 2B PENCIL ONLY',
            });
        } else if (block.type === 'signature') {
            boxes.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                x: mmToPx(block.x_mm),
                y: mmToPx(block.y_mm),
                width: mmToPx(block.w_mm),
                height: mmToPx(block.h_mm),
                tag: 'signature_box',
                scope: 'template',
                overflow: block.overflow ?? 'truncate',
                font_size: block.font_size ?? 9.0,
                content: block.content ?? block.title ?? 'EXAMINEE SIGNATURE',
            });
        } else if (block.type === 'metadata_box') {
            boxes.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                x: mmToPx(block.x_mm),
                y: mmToPx(block.y_mm),
                width: mmToPx(block.w_mm),
                height: mmToPx(block.h_mm),
                tag: 'metadata_box',
                scope: 'template',
                overflow: block.overflow ?? 'truncate',
                font_size: block.font_size ?? 10.0,
                content: block.content ?? block.title ?? 'EXAM METADATA',
            });
        } else if (block.type === 'custom_text' || block.type === 'text') {
            boxes.push({
                name: block.id,
                page_no: block.page_no ?? 1,
                x: mmToPx(block.x_mm),
                y: mmToPx(block.y_mm),
                width: mmToPx(block.w_mm),
                height: mmToPx(block.h_mm),
                tag: block.tag ?? 'custom_text',
                scope: block.scope ?? 'template',
                overflow: block.overflow ?? 'truncate',
                font_size: block.font_size ?? 11.0,
                content: block.content ?? block.title ?? 'CUSTOM TEXT',
            });
        }
    }

    return { boxes };
}

/**
 * Preview-only layout validation for immediate UI feedback
 */
export function validateStudioLayout(
    paper: { width_mm: number; height_mm: number; margin_mm: number },
    insets: InsetMargins,
    blocks: StudioBlock[],
): StudioValidationResult {
    const warnings: StudioValidationWarning[] = [];
    const m = paper.margin_mm;

    // Inset range validation
    const minInset = ALLOWED_INSET_RANGE_MM[0];
    const maxInset = ALLOWED_INSET_RANGE_MM[1];
    for (const [side, val] of Object.entries(insets)) {
        if (val < minInset || val > maxInset) {
            warnings.push({
                code: 'inset_out_of_range',
                message: `Marker inset on ${side.replace('_mm', '')} (${val}mm) must be between ${minInset}mm and ${maxInset}mm.`,
                severity: 'error',
            });
        }
    }

    // Margin breaches
    for (const b of blocks) {
        if (
            b.x_mm < m ||
            b.y_mm < m ||
            b.x_mm + b.w_mm > paper.width_mm - m ||
            b.y_mm + b.h_mm > paper.height_mm - m
        ) {
            warnings.push({
                block_id: b.id,
                code: 'margin_breach',
                message: `Block "${b.id}" extends beyond the ${m}mm printable margin.`,
                severity: 'warning',
            });
        }
    }

    for (const block of blocks) {
        if (
            block.rotation_deg !== 0 &&
            block.rotation_deg != null &&
            block.type !== 'roll' &&
            !block.option_points_mm?.length
        ) {
            warnings.push({
                block_id: block.id,
                code: 'tilted_points_missing',
                message: `Tilted question "${block.id}" has no explicit option points; saving an axis-aligned fallback is blocked.`,
                severity: 'error',
            });
        }
        if (
            block.identity_grid === true &&
            Number(block.rotation_deg ?? 0) !== 0 &&
            (!Number.isFinite(Number(block.rotation_deg)) ||
                Math.abs(Number(block.rotation_deg)) > 5)
        ) {
            warnings.push({
                block_id: block.id,
                code: 'identity_tilt_out_of_range',
                message: `Applicant Number grid "${block.id}" tilt must remain within ±5 degrees.`,
                severity: 'error',
            });
        }
        if (
            block.identity_grid === true &&
            block.roll_direction === 'horizontal' &&
            Number(block.rotation_deg ?? 0) !== 0
        ) {
            warnings.push({
                block_id: block.id,
                code: 'identity_tilt_unsupported',
                message: `Applicant Number grid "${block.id}" must be vertical to use machine-recognized tilt.`,
                severity: 'error',
            });
        }
    }

    const nativeMapping = emitNativeMapping(
        paper.width_mm,
        paper.height_mm,
        blocks,
    );
    const pageWidthPx = mmToPx(paper.width_mm);
    const pageHeightPx = mmToPx(paper.height_mm);
    for (const block of nativeMapping.blocks) {
        if (!block.bubble_positions) continue;

        const outOfBounds = block.bubble_positions.some((row) =>
            row.some(
                ([x, y]) =>
                    !Number.isInteger(x) ||
                    !Number.isInteger(y) ||
                    x < 0 ||
                    y < 0 ||
                    x + block.bubble_width_px > pageWidthPx ||
                    y + block.bubble_height_px > pageHeightPx,
            ),
        );
        if (outOfBounds) {
            warnings.push({
                block_id: block.name,
                code: 'bubble_out_of_bounds',
                message: `A tilted bubble in "${block.name}" extends beyond the paper boundary.`,
                severity: 'error',
            });
        }
    }

    // AABB collision detection among blocks
    for (let i = 0; i < blocks.length; i++) {
        for (let j = i + 1; j < blocks.length; j++) {
            const a = blocks[i];
            const b = blocks[j];
            if (a.page_no !== b.page_no) continue;
            const overlapX =
                a.x_mm < b.x_mm + b.w_mm && a.x_mm + a.w_mm > b.x_mm;
            const overlapY =
                a.y_mm < b.y_mm + b.h_mm && a.y_mm + a.h_mm > b.y_mm;
            if (overlapX && overlapY) {
                warnings.push({
                    block_id: a.id,
                    code: 'block_collision',
                    message: `Block "${a.id}" overlaps with block "${b.id}".`,
                    severity: 'error',
                });
            }
        }
    }

    return {
        valid: warnings.filter((w) => w.severity === 'error').length === 0,
        warnings,
    };
}
export interface UploadedInventoryDeclaration {
    id?: string;
    page_no: number | null;
    area_code: string;
    question_count: number | null;
    option_labels: string[];
    geometry_id?: string | null;
}

export interface UploadedIdentityDeclaration {
    method?: 'shaded_grid' | 'write_in_manual' | null;
    digit_count?: number | null;
    strategy?: string | null;
    has_bubbles?: boolean;
    has_write_in?: boolean;
    identity_pages?: Array<number | string>;
    regions?: Array<{
        page_no: number | null;
        x: number | null;
        y: number | null;
        w: number | null;
        h: number | null;
    }>;
    write_in_boxes?: Array<{
        page_no: number | null;
        x_mm?: number | null;
        y_mm?: number | null;
        w_mm?: number | null;
        h_mm?: number | null;
        [key: string]: unknown;
    }>;
}

export interface UploadedMappingSection {
    id: string;
    type: string;
    page_no?: number;
    aptitudeAreaCode?: string;
    questionsCount?: number | string | null;
    customValuesStr?: string;
    startQ?: number | string | null;
    posX?: number | string | null;
    posY?: number | string | null;
    bubbleDiameterMm?: number | string | null;
    bubblePitchMm?: number | string | null;
    rowPitchMm?: number | string | null;
    inventory_id?: string;
    identity_grid?: boolean;
}

export function assessUploadedMappingReadiness(input: {
    pageCount: number;
    inventory: UploadedInventoryDeclaration[];
    identity: UploadedIdentityDeclaration | null;
    sections: UploadedMappingSection[];
    pageWidthPx?: number;
    pageHeightPx?: number;
}): { ready: boolean; issues: string[] } {
    const issues: string[] = [];
    const declarationsByPage = new Map<number, UploadedInventoryDeclaration[]>();
    const usedGeometryIds = new Set<string>();

    for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
        declarationsByPage.set(
            pageNo,
            input.inventory.filter(
                (declaration) => Number(declaration.page_no) === pageNo,
            ),
        );
    }

    for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
        const declarations = declarationsByPage.get(pageNo) ?? [];
        if (declarations.length === 0) {
            issues.push(`Page ${pageNo}: declare its scored question inventory.`);
        }

        const areaCodes = new Set<string>();
        for (const declaration of declarations) {
            const areaCode = String(declaration.area_code ?? '').trim();
            if (!/^[A-Z][A-Z0-9_]*$/.test(areaCode)) {
                issues.push(
                    `Page ${pageNo}: enter an uppercase area code using letters, numbers, or underscores.`,
                );
            } else if (areaCodes.has(areaCode)) {
                issues.push(
                    `Page ${pageNo}: area code ${areaCode} is declared more than once; mapping is ambiguous.`,
                );
            } else {
                areaCodes.add(areaCode);
            }

            const questionCount = Number(declaration.question_count);
            if (
                !Number.isInteger(questionCount) ||
                questionCount < 1
            ) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: enter the exact question count.`,
                );
            }

            const optionLabels = Array.isArray(declaration.option_labels)
                ? declaration.option_labels.map((label) => String(label).trim())
                : [];
            if (optionLabels.length < 2 || optionLabels.some((label) => !label)) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: enter every option label.`,
                );
            }
            const normalizedOptionLabels = optionLabels.map((label) =>
                label.toUpperCase(),
            );
            if (
                normalizedOptionLabels.some(
                    (label, index) =>
                        normalizedOptionLabels.indexOf(label) !== index,
                )
            ) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: option labels contain a duplicate and are ambiguous.`,
                );
            }

            const matchingAreaSections = input.sections.filter(
                (candidate) =>
                    candidate &&
                    candidate.type !== 'roll' &&
                    Number(candidate.page_no) === pageNo &&
                    String(
                        (candidate as Record<string, unknown>).area_code ??
                            candidate.aptitudeAreaCode ??
                            '',
                    ).trim() === areaCode,
            );
            const summedAreaQuestions = matchingAreaSections.reduce(
                (sum, s) => sum + (Number(s.questionsCount) || 0),
                0,
            );

            const geometryId = String(declaration.geometry_id ?? '');
            if (!geometryId && matchingAreaSections.length === 0) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: map a recognition geometry block.`,
                );
                continue;
            }

            if (geometryId) {
                if (usedGeometryIds.has(geometryId)) {
                    issues.push(
                        `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: recognition geometry is assigned more than once; mapping is ambiguous.`,
                    );
                }
                usedGeometryIds.add(geometryId);
            }

            const primarySection =
                input.sections.find(
                    (candidate) => candidate.id === geometryId,
                ) ?? matchingAreaSections[0];
            if (
                !primarySection ||
                primarySection.type === 'roll' ||
                Number(primarySection.page_no) !== pageNo
            ) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: selected recognition geometry is missing or belongs to another page.`,
                );
                continue;
            }

            const primaryAreaCode = String(
                (primarySection as Record<string, unknown>).area_code ??
                    primarySection.aptitudeAreaCode ??
                    '',
            ).trim();
            if (primaryAreaCode !== areaCode) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: recognition geometry area code does not match its declaration.`,
                );
            }

            const effectiveMappedCount =
                matchingAreaSections.length > 1 &&
                primaryAreaCode === areaCode
                    ? summedAreaQuestions
                    : Number(primarySection.questionsCount);
            if (effectiveMappedCount !== questionCount) {
                issues.push(
                    `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: recognition geometry question count does not match its declaration.`,
                );
            }

            const sectionsToValidate =
                matchingAreaSections.length > 1 &&
                primaryAreaCode === areaCode
                    ? matchingAreaSections
                    : [primarySection];

            for (const section of sectionsToValidate) {
                const customLabels =
                    typeof section.customValuesStr === 'string' &&
                    section.customValuesStr.trim() !== ''
                        ? section.customValuesStr
                              .split(',')
                              .map((label) => label.trim())
                              .filter(Boolean)
                        : null;
                const pointLabels =
                    Array.isArray(section.optionPointsMm) &&
                    section.optionPointsMm.length >= 2
                        ? section.optionPointsMm
                              .map((p: unknown) =>
                                  typeof p === 'object' &&
                                  p !== null &&
                                  'label' in p &&
                                  typeof p.label === 'string'
                                      ? p.label.trim()
                                      : '',
                              )
                              .filter(Boolean)
                        : null;
                const rawOptionLabels = Array.isArray(section.optionLabels)
                    ? section.optionLabels
                    : null;
                const mappedOptionLabels =
                    customLabels ??
                    pointLabels ??
                    rawOptionLabels ??
                    (Array.isArray(section.bubble_values)
                        ? section.bubble_values
                        : []);

                if (mappedOptionLabels.length > 0 && optionLabels.length > 0) {
                    if (
                        mappedOptionLabels.length !== optionLabels.length ||
                        mappedOptionLabels.some(
                            (label, index) => label !== optionLabels[index],
                        )
                    ) {
                        issues.push(
                            `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: recognition geometry option labels do not match the declaration.`,
                        );
                        break;
                    }
                }
                for (const [label, value] of [
                    ['origin X', section.posX],
                    ['origin Y', section.posY],
                    ['bubble diameter', section.bubbleDiameterMm],
                    ['option pitch', section.bubblePitchMm],
                    ['row pitch', section.rowPitchMm],
                ] as const) {
                    const numericValue = Number(value);
                    if (
                        value === '' ||
                        value == null ||
                        !Number.isFinite(numericValue) ||
                        numericValue <= 0
                    ) {
                        issues.push(
                            `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ''}: set a positive ${label} for the mapped geometry.`,
                        );
                    }
                }
            }
        }
    }

    for (const declaration of input.inventory) {
        const pageNo = Number(declaration.page_no);
        if (!Number.isInteger(pageNo) || pageNo < 1 || pageNo > input.pageCount) {
            issues.push('An inventory area references a page that is not in this draft.');
        }
    }

    const identity = input.identity;
    const rawIdentityPages =
        Array.isArray(identity?.identity_pages) &&
        identity.identity_pages.length > 0
            ? identity.identity_pages
                  .map((page) => Number(page))
                  .filter(
                      (page) =>
                          Number.isInteger(page) &&
                          page >= 1 &&
                          page <= input.pageCount,
                  )
            : null;
    const identityPages =
        rawIdentityPages && rawIdentityPages.length > 0
            ? Array.from(new Set(rawIdentityPages))
            : Array.from({ length: input.pageCount }, (_, index) => index + 1);
    const hasBubbles = Boolean(
        identity?.has_bubbles ||
        identity?.strategy === 'bubbles_and_write_in' ||
        identity?.strategy === 'bubbles_only' ||
        identity?.method === 'shaded_grid',
    );
    const hasWriteIn = Boolean(
        identity?.has_write_in ||
        identity?.strategy === 'bubbles_and_write_in' ||
        identity?.strategy === 'write_in_only' ||
        identity?.method === 'write_in_manual' ||
        (Array.isArray(identity?.write_in_boxes) && identity.write_in_boxes.length > 0) ||
        (Array.isArray(identity?.regions) && identity.regions.length > 0),
    );

    if (!hasBubbles && !hasWriteIn) {
        issues.push('Select an explicit Applicant Number identity method.');
    }

    if (hasBubbles) {
        const digitCount = Number(identity?.digit_count);
        if (!Number.isInteger(digitCount) || digitCount < 2 || digitCount > 10) {
            issues.push(
                'Applicant Number grid: enter the exact digit count from the source.',
            );
        }
        for (const pageNo of identityPages) {
            const grids = input.sections.filter(
                (section) =>
                    section.identity_grid === true &&
                    section.type === 'roll' &&
                    Number(section.page_no) === pageNo,
            );
            if (grids.length === 0) {
                issues.push(
                    `Page ${pageNo}: map the vertical shaded Applicant Number grid.`,
                );
            } else if (grids.length > 1) {
                issues.push(
                    `Page ${pageNo}: multiple Applicant Number grids are mapped; identity is ambiguous.`,
                );
            }
            for (const grid of grids) {
                if (Number(grid.questionsCount) !== digitCount) {
                    issues.push(
                        `Page ${pageNo}: Applicant Number grid digit count does not match the identity declaration.`,
                    );
                }
                for (const [label, value] of [
                    ['origin X', grid.posX],
                    ['origin Y', grid.posY],
                    ['bubble diameter', grid.bubbleDiameterMm],
                    ['digit pitch', grid.bubblePitchMm],
                    ['row pitch', grid.rowPitchMm],
                ] as const) {
                    const numericValue = Number(value);
                    if (
                        value === '' ||
                        value == null ||
                        !Number.isFinite(numericValue) ||
                        numericValue <= 0
                    ) {
                        issues.push(
                            `Page ${pageNo}: set a positive ${label} for the Applicant Number grid.`,
                        );
                    }
                }
            }
        }
    }

    if (hasWriteIn) {
        const boxes = Array.isArray(identity?.write_in_boxes) ? identity.write_in_boxes : [];
        const regions = Array.isArray(identity?.regions) ? identity.regions : [];

        if (boxes.length > 0) {
            for (const pageNo of identityPages) {
                const pageBoxes = boxes.filter(
                    (box) => Number(box.page_no) === pageNo,
                );
                if (pageBoxes.length === 0) {
                    issues.push(
                        `Page ${pageNo}: map the visible handwritten Applicant Number box.`,
                    );
                } else if (pageBoxes.length > 1) {
                    issues.push(
                        `Page ${pageNo}: multiple handwritten Applicant Number boxes are mapped; identity is ambiguous.`,
                    );
                }
                for (const box of pageBoxes) {
                    const x = Number(box.x_mm);
                    const y = Number(box.y_mm);
                    const width = Number(box.w_mm);
                    const height = Number(box.h_mm);
                    if (
                        !Number.isFinite(x) ||
                        !Number.isFinite(y) ||
                        !Number.isFinite(width) ||
                        !Number.isFinite(height) ||
                        x < 0 ||
                        y < 0 ||
                        width <= 0 ||
                        height <= 0
                    ) {
                        issues.push(
                            `Page ${pageNo}: set valid positive dimensions for the handwritten Applicant Number box.`,
                        );
                    } else if (x + width > 210 || y + height > 297) {
                        issues.push(
                            `Page ${pageNo}: handwritten Applicant Number box extends beyond the page.`,
                        );
                    }
                }
            }
        } else if (regions.length > 0) {
            for (const pageNo of identityPages) {
                const pageRegions = regions.filter(
                    (region) => Number(region.page_no) === pageNo,
                );
                if (pageRegions.length === 0) {
                    issues.push(
                        `Page ${pageNo}: map the visible write-in Applicant Number region.`,
                    );
                } else if (pageRegions.length > 1) {
                    issues.push(
                        `Page ${pageNo}: multiple write-in Applicant Number regions are mapped; identity is ambiguous.`,
                    );
                }
                for (const region of pageRegions) {
                    const x = Number(region.x);
                    const y = Number(region.y);
                    const width = Number(region.w);
                    const height = Number(region.h);
                    if (
                        !Number.isFinite(x) ||
                        !Number.isFinite(y) ||
                        !Number.isFinite(width) ||
                        !Number.isFinite(height) ||
                        x < 0 ||
                        y < 0 ||
                        width <= 0 ||
                        height <= 0
                    ) {
                        issues.push(
                            `Page ${pageNo}: enter a visible write-in region with non-negative position and positive size in recognition pixels.`,
                        );
                    } else if (
                        x + width > (input.pageWidthPx ?? mmToPx(210)) ||
                        y + height > (input.pageHeightPx ?? mmToPx(297))
                    ) {
                        issues.push(
                            `Page ${pageNo}: write-in Applicant Number region extends beyond the reference page.`,
                        );
                    }
                }
            }
        } else {
            issues.push(
                'Handwritten Applicant Number box: map at least one review box on the canvas.',
            );
        }
    }

    return { ready: issues.length === 0, issues };
}

export interface NormalizedCornerPoint {
    x: number;
    y: number;
}

/**
 * Snap all 4 normalized crop corners to the sheet rectangle with a bounded inset.
 * Inset 0.02 = 2% inner margin preset; inset 0.002 = full image bounds.
 */
export function resetPhotoCropCorners(
    inset = 0.02,
): [
    NormalizedCornerPoint,
    NormalizedCornerPoint,
    NormalizedCornerPoint,
    NormalizedCornerPoint,
] {
    const clamped = Math.max(0.001, Math.min(0.2, Number(inset) || 0.02));
    const far = Number((1 - clamped).toFixed(4));
    const near = Number(clamped.toFixed(4));
    return [
        { x: near, y: near },
        { x: far, y: near },
        { x: far, y: far },
        { x: near, y: far },
    ];
}

/**
 * Rotate 4 clockwise normalized corners [TL, TR, BR, BL] by 90 degrees clockwise
 * while preserving the canonical [TL, TR, BR, BL] vertex ordering.
 */
export function rotatePhotoCropCornersClockwise(
    corners: NormalizedCornerPoint[],
): [
    NormalizedCornerPoint,
    NormalizedCornerPoint,
    NormalizedCornerPoint,
    NormalizedCornerPoint,
] {
    const base =
        Array.isArray(corners) && corners.length === 4
            ? corners
            : resetPhotoCropCorners(0.02);
    const rotated = base.map((pt) => ({
        x: Number(Math.max(0.001, Math.min(0.999, 1 - pt.y)).toFixed(4)),
        y: Number(Math.max(0.001, Math.min(0.999, pt.x)).toFixed(4)),
    }));
    // After 90° CW rotation, old BL (index 3) becomes the new TL (index 0)
    return [rotated[3], rotated[0], rotated[1], rotated[2]];
}

/**
 * Detect the 4 corners of a bright rectangular paper sheet in a static RGBA image buffer.
 * Uses Otsu thresholding combined with Sobel gradient edge suppression so table/floor
 * reflections do not bleed into the paper component, and supports close-up photos up to 97% area.
 */
export function detectStaticPaperCornersFromPixels(
    data: Uint8ClampedArray,
    width: number,
    height: number,
):
    | [
          NormalizedCornerPoint,
          NormalizedCornerPoint,
          NormalizedCornerPoint,
          NormalizedCornerPoint,
      ]
    | null {
    if (width < 32 || height < 32 || data.length !== width * height * 4) {
        return null;
    }
    const size = width * height;
    const luma = new Uint8Array(size);
    const hist = new Uint32Array(256);

    for (let i = 0; i < size; i++) {
        const offset = i * 4;
        const y =
            (77 * data[offset] +
                150 * data[offset + 1] +
                29 * data[offset + 2]) >>
            8;
        luma[i] = y;
        hist[y]++;
    }

    // Otsu threshold to separate bright paper from darker desk/floor
    let sumAll = 0;
    for (let t = 0; t < 256; t++) sumAll += t * hist[t];
    let sumB = 0;
    let wB = 0;
    let maxVar = -1;
    let otsu = 140;
    for (let t = 0; t < 256; t++) {
        wB += hist[t];
        if (wB === 0) continue;
        const wF = size - wB;
        if (wF === 0) break;
        sumB += t * hist[t];
        const mB = sumB / wB;
        const mF = (sumAll - sumB) / wF;
        const between = wB * wF * (mB - mF) * (mB - mF);
        if (between > maxVar) {
            maxVar = between;
            otsu = t;
        }
    }

    const threshold = Math.max(90, Math.min(235, otsu));
    const mask = new Uint8Array(size);
    for (let y = 1; y < height - 1; y++) {
        for (let x = 1; x < width - 1; x++) {
            const idx = y * width + x;
            if (luma[idx] < threshold) continue;
            const gx = Math.abs(luma[idx + 1] - luma[idx - 1]);
            const gy = Math.abs(luma[idx + width] - luma[idx - width]);
            if (gx + gy < 95) {
                mask[idx] = 1;
            }
        }
    }

    const visited = new Uint8Array(size);
    const stack = new Int32Array(size);
    let bestArea = 0;
    let bestCorners: [[number, number], [number, number], [number, number], [number, number]] | null = null;

    for (let start = 0; start < size; start++) {
        if (!mask[start] || visited[start]) continue;
        visited[start] = 1;
        stack[0] = start;
        let sp = 1;
        let area = 0;
        let tlScore = Infinity;
        let trScore = -Infinity;
        let brScore = -Infinity;
        let blScore = Infinity;
        const corners: [[number, number], [number, number], [number, number], [number, number]] = [
            [0, 0],
            [0, 0],
            [0, 0],
            [0, 0],
        ];

        while (sp > 0) {
            const pos = stack[--sp];
            const x = pos % width;
            const y = (pos - x) / width;
            area++;
            if (x + y < tlScore) {
                tlScore = x + y;
                corners[0] = [x, y];
            }
            if (x - y > trScore) {
                trScore = x - y;
                corners[1] = [x, y];
            }
            if (x + y > brScore) {
                brScore = x + y;
                corners[2] = [x, y];
            }
            if (x - y < blScore) {
                blScore = x - y;
                corners[3] = [x, y];
            }

            const neighbors = [pos - 1, pos + 1, pos - width, pos + width];
            for (const n of neighbors) {
                if (n >= 0 && n < size && mask[n] && !visited[n]) {
                    visited[n] = 1;
                    stack[sp++] = n;
                }
            }
        }

        const fraction = area / size;
        if (fraction >= 0.12 && fraction <= 0.97 && area > bestArea) {
            bestArea = area;
            bestCorners = corners;
        }
    }

    if (!bestCorners) return null;
    return bestCorners.map(([x, y]) => ({
        x: Number(Math.max(0.005, Math.min(0.995, (x + 0.5) / width)).toFixed(4)),
        y: Number(Math.max(0.005, Math.min(0.995, (y + 0.5) / height)).toFixed(4)),
    })) as [
        NormalizedCornerPoint,
        NormalizedCornerPoint,
        NormalizedCornerPoint,
        NormalizedCornerPoint,
    ];
}

export interface AutoGridSolveInput {
    kind: RecognitionGeometryKind;
    firstPointMm: { x: number; y: number };
    lastPointMm: { x: number; y: number };
    columns: number;
    rows: number;
    bubbleDiameterMm?: number | null;
}

export interface AutoGridSolveResult {
    ok: boolean;
    error?: string;
    posX?: number;
    posY?: number;
    bubblePitchMm?: number;
    rowPitchMm?: number;
    bubbleDiameterMm?: number;
}

/**
 * Solve block origin (posX, posY) and 300-PPI pixel-quantized option/row pitches
 * from two diagonal bubble-center clicks (top-left bubble 1, bottom-right bubble N).
 * Centers any sub-pixel quantization residual across the full span so maximum
 * cumulative drift is halved across long 20–50 question columns.
 */
export function solveAutoGridFromTwoPoints(
    input: AutoGridSolveInput,
): AutoGridSolveResult {
    const columns = Math.max(1, Math.round(input.columns));
    const rows = Math.max(1, Math.round(input.rows));
    const { firstPointMm, lastPointMm } = input;

    if (
        !Number.isFinite(firstPointMm?.x) ||
        !Number.isFinite(firstPointMm?.y) ||
        !Number.isFinite(lastPointMm?.x) ||
        !Number.isFinite(lastPointMm?.y)
    ) {
        return { ok: false, error: 'Invalid anchor coordinates.' };
    }

    const dxMm = lastPointMm.x - firstPointMm.x;
    const dyMm = lastPointMm.y - firstPointMm.y;

    if ((columns > 1 && dxMm <= 2) || (rows > 1 && dyMm <= 2)) {
        return {
            ok: false,
            error: 'Second point must be below and to the right of the first bubble.',
        };
    }

    const rawXPitchMm = columns > 1 ? dxMm / (columns - 1) : 6.0;
    const rawYPitchMm = rows > 1 ? dyMm / (rows - 1) : 6.8;

    if (
        rawXPitchMm < 3.0 ||
        rawYPitchMm < 3.0 ||
        rawXPitchMm > 30 ||
        rawYPitchMm > 35
    ) {
        return {
            ok: false,
            error: 'Solved bubble spacing is outside physical answer-sheet bounds (3.0mm–30.0mm).',
        };
    }

    const requestedBubble =
        Number(input.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
    const minPitchRequest = Math.min(rawXPitchMm, rawYPitchMm);
    const bubbleDiameterMm = Number(
        Math.max(
            MIN_BUBBLE_MM,
            Math.min(requestedBubble, minPitchRequest - MIN_OPTION_GAP_MM),
        ).toFixed(2),
    );

    const effective = effectiveRecognitionGeometryMm({
        kind: input.kind,
        bubbleDiameterMm,
        bubblePitchMm: Number(rawXPitchMm.toFixed(2)),
        rowPitchMm: Number(rawYPitchMm.toFixed(2)),
    });

    const xPitchPx = mmToPx(effective.bubblePitchMm);
    const yPitchPx = mmToPx(effective.rowPitchMm);
    const bubblePx = mmToPx(effective.bubbleDiameterMm);

    const firstTopLeftXPx =
        (firstPointMm.x - effective.bubbleDiameterMm / 2) * PX_PER_MM;
    const firstTopLeftYPx =
        (firstPointMm.y - effective.bubbleDiameterMm / 2) * PX_PER_MM;
    const lastTopLeftXPx =
        (lastPointMm.x - effective.bubbleDiameterMm / 2) * PX_PER_MM;
    const lastTopLeftYPx =
        (lastPointMm.y - effective.bubbleDiameterMm / 2) * PX_PER_MM;

    const centeredOriginXPx =
        columns > 1
            ? Math.round(
                  (firstTopLeftXPx +
                      lastTopLeftXPx -
                      (columns - 1) * xPitchPx) /
                      2,
              )
            : Math.round(firstTopLeftXPx);
    const centeredOriginYPx =
        rows > 1
            ? Math.round(
                  (firstTopLeftYPx + lastTopLeftYPx - (rows - 1) * yPitchPx) /
                      2,
              )
            : Math.round(firstTopLeftYPx);

    const offset =
        input.kind === 'roll'
            ? ROLL_BUBBLE_ORIGIN_OFFSET_MM
            : GRID_BUBBLE_ORIGIN_OFFSET_MM;

    const posX = Number(
        Math.max(0, centeredOriginXPx / PX_PER_MM - offset.x).toFixed(2),
    );
    const posY = Number(
        Math.max(0, centeredOriginYPx / PX_PER_MM - offset.y).toFixed(2),
    );

    return {
        ok: true,
        posX,
        posY,
        bubblePitchMm: pxToMm(xPitchPx),
        rowPitchMm: pxToMm(yPitchPx),
        bubbleDiameterMm: pxToMm(bubblePx),
    };
}

export interface OptionPointMm {
    label: string;
    x_mm: number;
    y_mm: number;
}

export interface PlottedItemSection {
    id: string;
    name: string;
    type: 'mcq' | 'tf' | 'likert' | 'custom';
    page_no: number;
    area_code: string;
    aptitudeAreaCode: string;
    subtest_code?: string;
    test_instrument_subtest_id?: number;
    minBubbleMm?: number;
    startQ: number;
    questionsCount: number;
    optionsCount: number;
    customValuesStr: string;
    posX: number;
    posY: number;
    w_mm: number;
    h_mm: number;
    bubbleDiameterMm: number;
    bubblePitchMm: number;
    rowPitchMm: number;
    cols: number;
    isLocked: boolean;
    optionPointsMm: OptionPointMm[];
    rotationDeg?: number;
}

export interface SmartSnapSearchZoneOptions {
    pageNo: number;
    paperWidthMm?: number;
    paperHeightMm?: number;
    searchRadiusMm?: number;
    minBubbleMm?: number;
    activeSectionIndex?: number | null;
    selectedSectionIndices?: number[];
    scope?: 'selected' | 'all_unlocked';
    inspectedQuestionIndex?: number;
}

export interface SmartSnapSearchZoneOverlay {
    radiusMm: number;
    envelopes: Array<{
        key: string;
        sectionIndex: number;
        questionIndex: number;
        questionNumber: number | null;
        isDetailed: boolean;
        kind: 'capsule' | 'rect';
        cxMm: number;
        cyMm: number;
        widthMm: number;
        heightMm: number;
        rxMm: number;
        angleDeg: number;
    }>;
    halos: Array<{
        key: string;
        label: string;
        xMm: number;
        yMm: number;
        bubbleRadiusMm: number;
        searchRadiusMm: number;
    }>;
    leader: {
        x1Mm: number;
        y1Mm: number;
        x2Mm: number;
        y2Mm: number;
        labelXMm: number;
        labelYMm: number;
        text: string;
    } | null;
    selectedIsLocked: boolean;
    hasEligibleSelection: boolean;
}

/** Runtime boundary adapter: canvas section data also carries lock flags,
 * option labels, nested point rows, and roll grids outside the plotted-item type. */
type SearchZoneSectionInput = Omit<PlottedItemSection, 'type' | 'optionPointsMm'> & {
    type: string;
    locked?: boolean;
    identity_grid?: boolean;
    optionLabels?: string[];
    optionPointsMm?: unknown[];
};

type SearchZonePointInput = {
    label?: unknown;
    x_mm?: unknown;
    y_mm?: unknown;
};

/** Pure paper-space geometry; SVG clipping never clamps bubble centers or radii. */
export function buildSmartSnapSearchZoneOverlay(
    sections: PlottedItemSection[],
    options: SmartSnapSearchZoneOptions,
): SmartSnapSearchZoneOverlay {
    const requestedRadius = options.searchRadiusMm === undefined ? 5 : Number(options.searchRadiusMm);
    const radiusMm = Math.max(2, Math.min(12, Number.isFinite(requestedRadius) ? requestedRadius : 5));
    const result: SmartSnapSearchZoneOverlay = {
        radiusMm,
        envelopes: [],
        halos: [],
        leader: null,
        selectedIsLocked: false,
        hasEligibleSelection: false,
    };
    const selected = new Set(options.selectedSectionIndices ?? []);
    const activeIndex = options.activeSectionIndex;
    if (typeof activeIndex === 'number' && Number.isInteger(activeIndex) && activeIndex >= 0) {
        selected.add(activeIndex);
    }
    const paperWidthMm = Number(options.paperWidthMm) > 0 && Number.isFinite(Number(options.paperWidthMm))
        ? Number(options.paperWidthMm) : 210;
    const paperHeightMm = Number(options.paperHeightMm) > 0 && Number.isFinite(Number(options.paperHeightMm))
        ? Number(options.paperHeightMm) : 297;
    let leaderUsesActiveSection = false;

    sections.forEach((section, sectionIndex) => {
        const source: SearchZoneSectionInput = section;
        if (!source || Number(source.page_no ?? 1) !== Number(options.pageNo)) return;
        const isSelected = selected.has(sectionIndex);
        const isLocked = Boolean(source.locked || source.isLocked);
        if (isSelected && isLocked) result.selectedIsLocked = true;
        if (source.type === 'roll' || source.identity_grid || isLocked) return;
        if (isSelected) result.hasEligibleSelection = true;
        if (options.scope !== 'all_unlocked' && !isSelected) return;

        const rawPoints = Array.isArray(source.optionPointsMm) ? source.optionPointsMm : [];
        const hasNestedRows = rawPoints.length > 0 && rawPoints.every(Array.isArray);
        const pointRows: unknown[][] = hasNestedRows ? rawPoints as unknown[][] : [];
        const flatPoints: unknown[] = hasNestedRows ? pointRows.flat() : rawPoints;
        let labels: string[];
        if (Array.isArray(source.optionLabels) && source.optionLabels.length > 0) {
            labels = source.optionLabels.map(String);
        } else {
            const customLabels = typeof source.customValuesStr === 'string'
                ? source.customValuesStr.split(',').map((label) => label.trim()).filter(Boolean)
                : [];
            if (customLabels.length > 0) {
                labels = customLabels;
            } else {
                const pointLabels = [...new Set(flatPoints
                    .map((point) => point !== null && typeof point === 'object' && !Array.isArray(point)
                        ? String((point as SearchZonePointInput).label ?? '').trim()
                        : '')
                    .filter(Boolean))];
                labels = pointLabels.length > 0 ? pointLabels : Array.from(
                    { length: Math.max(2, Number(source.optionsCount) || 4) },
                    (_, index) => String.fromCharCode(65 + index),
                );
            }
        }
        const questionCount = Math.max(1, Number(source.questionsCount) || 1);
        const diameterMm = Number(source.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
        const bubbleRadiusMm = diameterMm / 2;
        const geometry = effectiveRecognitionGeometryMm({
            kind: 'grid',
            bubbleDiameterMm: diameterMm,
            bubblePitchMm: source.bubblePitchMm,
            rowPitchMm: source.rowPitchMm,
            minBubbleMm: options.minBubbleMm ?? source.minBubbleMm ?? MIN_BUBBLE_MM,
        });
        const explicitPoints = flatPoints.length === questionCount * labels.length
            && (!hasNestedRows || (pointRows.length === questionCount
                && pointRows.every((row) => row.length === labels.length)))
            ? flatPoints : null;
        const requestedInspectedIndex = Number(options.inspectedQuestionIndex ?? 0);
        const inspectedIndex = Math.max(0, Math.min(
            Math.ceil(questionCount) - 1,
            Number.isFinite(requestedInspectedIndex) ? Math.floor(requestedInspectedIndex) : 0,
        ));
        const isActive = activeIndex === sectionIndex;

        for (let questionIndex = 0; questionIndex < questionCount; questionIndex++) {
            const points = labels.map((label, optionIndex) => {
                const rawPoint = explicitPoints?.[questionIndex * labels.length + optionIndex];
                const point = rawPoint !== null && typeof rawPoint === 'object' && !Array.isArray(rawPoint)
                    ? rawPoint as SearchZonePointInput
                    : null;
                return {
                    label: String(point?.label ?? label),
                    xMm: point && Number.isFinite(Number(point.x_mm))
                        ? Number(point.x_mm)
                        : (Number(source.posX) || 0) + GRID_BUBBLE_ORIGIN_OFFSET_MM.x
                            + bubbleRadiusMm + optionIndex * geometry.bubblePitchMm,
                    yMm: point && Number.isFinite(Number(point.y_mm))
                        ? Number(point.y_mm)
                        : (Number(source.posY) || 0) + GRID_BUBBLE_ORIGIN_OFFSET_MM.y
                            + bubbleRadiusMm + questionIndex * geometry.rowPitchMm,
                };
            });
            if (points.length === 0 || !Number.isFinite(bubbleRadiusMm) || bubbleRadiusMm <= 0
                || points.some((point) => !Number.isFinite(point.xMm) || !Number.isFinite(point.yMm))) continue;
            const startQuestion = Number(source.startQ);
            const questionNumber = Number.isInteger(startQuestion) && startQuestion > 0
                ? startQuestion + questionIndex : null;
            const isDetailed = isSelected && (questionCount === 1 || (isActive && questionIndex === inspectedIndex));
            const key = `section:${sectionIndex}:${source.id}:question:${questionIndex}`;
            const first = points[0];
            const last = points[points.length - 1];
            const dx = last.xMm - first.xMm;
            const dy = last.yMm - first.yMm;
            const endpointSpan = Math.hypot(dx, dy);
            const ux = endpointSpan > 0 ? dx / endpointSpan : 1;
            const uy = endpointSpan > 0 ? dy / endpointSpan : 0;
            const projections = points.map((point) => (point.xMm - first.xMm) * ux + (point.yMm - first.yMm) * uy);
            const nearCollinear = endpointSpan > 0
                ? points.every((point) => Math.abs((point.xMm - first.xMm) * uy - (point.yMm - first.yMm) * ux) <= 0.35)
                : points.every((point) => point.xMm === first.xMm && point.yMm === first.yMm);
            const padding = bubbleRadiusMm + radiusMm;
            let cxMm: number;
            let cyMm: number;
            let widthMm: number;
            let heightMm: number;
            let angleDeg = 0;
            if (nearCollinear) {
                const minimumProjection = Math.min(...projections);
                const maximumProjection = Math.max(...projections);
                const midProjection = (minimumProjection + maximumProjection) / 2;
                cxMm = first.xMm + ux * midProjection;
                cyMm = first.yMm + uy * midProjection;
                widthMm = maximumProjection - minimumProjection + 2 * padding;
                heightMm = 2 * padding;
                angleDeg = Math.atan2(dy, dx) * 180 / Math.PI;
            } else {
                const minX = Math.min(...points.map((point) => point.xMm));
                const maxX = Math.max(...points.map((point) => point.xMm));
                const minY = Math.min(...points.map((point) => point.yMm));
                const maxY = Math.max(...points.map((point) => point.yMm));
                cxMm = (minX + maxX) / 2;
                cyMm = (minY + maxY) / 2;
                widthMm = maxX - minX + 2 * padding;
                heightMm = maxY - minY + 2 * padding;
            }
            result.envelopes.push({
                key, sectionIndex, questionIndex, questionNumber, isDetailed,
                kind: nearCollinear ? 'capsule' : 'rect',
                cxMm, cyMm, widthMm, heightMm, rxMm: padding, angleDeg,
            });
            if (!isDetailed) continue;
            points.forEach((point, optionIndex) => result.halos.push({
                key: `${key}:option:${optionIndex}`,
                label: point.label,
                xMm: point.xMm,
                yMm: point.yMm,
                bubbleRadiusMm,
                searchRadiusMm: radiusMm,
            }));
            if (result.leader && (!isActive || leaderUsesActiveSection)) continue;
            const anchor = points.reduce((rightmost, point) => point.xMm > rightmost.xMm ? point : rightmost);
            const direction = anchor.xMm + radiusMm <= paperWidthMm ? 1 : -1;
            const text = `${questionNumber === null ? '' : `Q${questionNumber} • `}${radiusMm.toFixed(1)} mm search`;
            const halfLabelWidth = Math.min(paperWidthMm / 2, text.length * 0.85);
            result.leader = {
                x1Mm: anchor.xMm,
                y1Mm: anchor.yMm,
                x2Mm: anchor.xMm + direction * radiusMm,
                y2Mm: anchor.yMm,
                labelXMm: Math.max(halfLabelWidth, Math.min(paperWidthMm - halfLabelWidth, (anchor.xMm + anchor.xMm + direction * radiusMm) / 2)),
                labelYMm: Math.max(3, Math.min(paperHeightMm - 3, anchor.yMm - 1.5)),
                text,
            };
            leaderUsesActiveSection = isActive;
        }
    });
    return result;
}

/**
 * Normalize an aptitude area code to a valid uppercase identifier (/^[A-Z][A-Z0-9_]*$/).
 */
export function normalizeOmrAreaCode(raw: string | null | undefined): string {
    const cleaned = String(raw ?? '')
        .trim()
        .toUpperCase()
        .replace(/[^A-Z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '');
    if (!cleaned) return 'GENERAL';
    return /^[A-Z]/.test(cleaned) ? cleaned : `AREA_${cleaned}`;
}

/**
 * Collect all plotted item question numbers for a given subtest code (or all subtests if subtestCode omitted).
 * Handles multi-question sections (e.g. grids with startQ + questionsCount).
 */
export function subtestPlottedNumbers(
    sections: Array<Record<string, unknown>>,
    subtestCode?: string | null,
): Set<number> {
    const targetSubtest = subtestCode ? normalizeOmrAreaCode(subtestCode) : null;
    const usedNumbers = new Set<number>();
    for (const sec of sections) {
        if (!sec || sec.type === 'roll' || sec.identity_grid) continue;
        const secArea = normalizeOmrAreaCode((sec.area_code ?? sec.aptitudeAreaCode) as string);
        const secSubtest = sec.subtest_code ? normalizeOmrAreaCode(sec.subtest_code as string) : null;

        const matches = targetSubtest
            ? (secSubtest === targetSubtest || secArea === targetSubtest)
            : true;

        if (!matches) continue;
        const start = Math.max(1, Number(sec.startQ) || 1);
        const count = Math.max(1, Number(sec.questionsCount) || 1);
        for (let i = 0; i < count; i++) {
            usedNumbers.add(start + i);
        }
    }
    return usedNumbers;
}

/**
 * Compute the next auto-incremented item number (1, 2, 3...) for a given aptitude area or subtest,
 * using the "lowest missing integer in [1, N]" (fill-in-the-gap) algorithm.
 * If no questions exist for the subtest/area, returns 1. If gaps exist (e.g. 1, 2, 4), returns 3.
 */
export function nextItemNumberForArea(
    sections: Array<Record<string, unknown>>,
    areaCode: string | null | undefined,
    subtestCode?: string | null | undefined,
): number {
    const targetArea = normalizeOmrAreaCode(areaCode);
    const targetSubtest = subtestCode ? normalizeOmrAreaCode(subtestCode) : null;
    const usedNumbers = new Set<number>();

    for (const sec of sections) {
        if (!sec || sec.type === 'roll' || sec.identity_grid) continue;
        const secArea = normalizeOmrAreaCode((sec.area_code ?? sec.aptitudeAreaCode) as string);
        const secSubtest = sec.subtest_code ? normalizeOmrAreaCode(sec.subtest_code as string) : null;

        const matches = targetSubtest
            ? (secSubtest === targetSubtest || secArea === targetSubtest)
            : (secArea === targetArea);

        if (!matches) continue;
        const start = Math.max(1, Number(sec.startQ) || 1);
        const count = Math.max(1, Number(sec.questionsCount) || 1);
        for (let i = 0; i < count; i++) {
            usedNumbers.add(start + i);
        }
    }

    if (usedNumbers.size === 0) return 1;

    let k = 1;
    while (usedNumbers.has(k)) {
        k++;
    }
    return k;
}
/**
 * Build a solidified 1-question item component from consecutive clicked option center points.
 * Preserves both exact per-option visual coordinates and 300-PPI native OMRChecker geometry.
 */
export function buildSolidItemFromOptionPoints(input: {
    id?: string;
    pageNo?: number;
    areaCode?: string | null;
    subtest_code?: string | null;
    test_instrument_subtest_id?: number | string | null;
    itemNumber: number;
    optionLabels: string[];
    pointsMm: Array<{ x: number; y: number }>;
    bubbleDiameterMm?: number | null;
    minBubbleMm?: number;
    rotationDeg?: number;
}): PlottedItemSection {
    const pageNo = Math.max(1, Math.round(Number(input.pageNo) || 1));
    const areaCode = normalizeOmrAreaCode(input.areaCode);
    const itemNumber = Math.max(1, Math.round(Number(input.itemNumber) || 1));
    const labels =
        Array.isArray(input.optionLabels) && input.optionLabels.length >= 2
            ? input.optionLabels.map((l) => String(l).trim()).filter(Boolean)
            : ['A', 'B', 'C', 'D'];
    const count = labels.length;
    const minBubble = input.minBubbleMm ?? MIN_BUBBLE_MM;

    const requestedDiameter =
        Number(input.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
    const clampedDiameter = Number(
        Math.max(minBubble, Math.min(9.0, requestedDiameter)).toFixed(2),
    );

    const optionPointsMm: OptionPointMm[] = labels.map((label, idx) => {
        const pt = input.pointsMm[idx] ??
            input.pointsMm[input.pointsMm.length - 1] ?? {
                x: 30 + idx * 6,
                y: 60,
            };
        return {
            label,
            x_mm: Number(Math.max(2, Math.min(208, Number(pt.x) || 30)).toFixed(2)),
            y_mm: Number(Math.max(2, Math.min(295, Number(pt.y) || 60)).toFixed(2)),
        };
    });

    const firstPt = optionPointsMm[0];
    const lastPt = optionPointsMm[optionPointsMm.length - 1];
    const avgY =
        optionPointsMm.reduce((acc, pt) => acc + pt.y_mm, 0) /
        Math.max(1, optionPointsMm.length);

    const rawSpanX = Math.abs(lastPt.x_mm - firstPt.x_mm);
    const rawPitchMm =
        count > 1 && rawSpanX > 1 ? rawSpanX / (count - 1) : 6.0;

    const effective = effectiveRecognitionGeometryMm({
        kind: 'grid',
        bubbleDiameterMm: clampedDiameter,
        bubblePitchMm: Number(rawPitchMm.toFixed(2)),
        rowPitchMm: 6.8,
        minBubbleMm: minBubble,
    });

    const xPitchPx = mmToPx(effective.bubblePitchMm);
    const bubblePx = mmToPx(effective.bubbleDiameterMm);
    const minCenterX = Math.min(firstPt.x_mm, lastPt.x_mm);
    const maxCenterX = Math.max(firstPt.x_mm, lastPt.x_mm);

    const firstTopLeftXPx =
        (minCenterX - effective.bubbleDiameterMm / 2) * PX_PER_MM;
    const lastTopLeftXPx =
        (maxCenterX - effective.bubbleDiameterMm / 2) * PX_PER_MM;
    const centeredOriginXPx =
        count > 1
            ? Math.round(
                  (firstTopLeftXPx + lastTopLeftXPx - (count - 1) * xPitchPx) /
                      2,
              )
            : Math.round(firstTopLeftXPx);
    const originYPx = Math.round(
        (avgY - effective.bubbleDiameterMm / 2) * PX_PER_MM,
    );

    const posX = Number(
        Math.max(
            0,
            centeredOriginXPx / PX_PER_MM - GRID_BUBBLE_ORIGIN_OFFSET_MM.x,
        ).toFixed(2),
    );
    const posY = Number(
        Math.max(
            0,
            originYPx / PX_PER_MM - GRID_BUBBLE_ORIGIN_OFFSET_MM.y,
        ).toFixed(2),
    );

    const bubblePitchMm = pxToMm(xPitchPx);
    const bubbleDiameterMm = Number(effective.bubbleDiameterMm.toFixed(2));
    const w_mm = Number(
        (bubbleDiameterMm + Math.max(0, count - 1) * bubblePitchMm).toFixed(2),
    );
    const h_mm = Number(bubbleDiameterMm.toFixed(2));

    const id = input.id || `${areaCode}_P${pageNo}_Q${itemNumber}`;
    return {
        id,
        name: `${areaCode} · Q${itemNumber}`,
        type: 'mcq',
        page_no: pageNo,
        area_code: areaCode,
        aptitudeAreaCode: areaCode,
        subtest_code: input.subtest_code ?? undefined,
        test_instrument_subtest_id:
            input.test_instrument_subtest_id != null
                ? Number(input.test_instrument_subtest_id)
                : undefined,
        startQ: itemNumber,
        questionsCount: 1,
        optionsCount: count,
        customValuesStr: labels.join(', '),
        posX,
        posY,
        w_mm,
        h_mm,
        bubbleDiameterMm,
        bubblePitchMm,
        rowPitchMm: 6.8,
        cols: 1,
        isLocked: false,
        optionPointsMm,
        rotationDeg:
            typeof input.rotationDeg === 'number'
                ? input.rotationDeg
                : undefined,
    };
}

/**
 * Update a single option point (e.g. re-spotting Option B) on an existing solidified item
 * and recompute its 300-PPI native origin and pitch.
 */
export function updateSolidItemOptionPoint(
    section: PlottedItemSection,
    optionIndex: number,
    newPointMm: { x: number; y: number },
): PlottedItemSection {
    const labels = (section.customValuesStr || 'A, B, C, D')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    const existingPoints =
        Array.isArray(section.optionPointsMm) &&
        section.optionPointsMm.length === labels.length
            ? section.optionPointsMm.map((pt) => ({ x: pt.x_mm, y: pt.y_mm }))
            : labels.map((_, idx) => ({
                  x:
                      Number(section.posX) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.x +
                      Number(section.bubbleDiameterMm) / 2 +
                      idx * Number(section.bubblePitchMm),
                  y:
                      Number(section.posY) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.y +
                      Number(section.bubbleDiameterMm) / 2,
              }));

    const clampedIndex = Math.max(
        0,
        Math.min(existingPoints.length - 1, optionIndex),
    );
    existingPoints[clampedIndex] = {
        x: Number(newPointMm.x),
        y: Number(newPointMm.y),
    };

    return buildSolidItemFromOptionPoints({
        id: section.id,
        pageNo: section.page_no,
        areaCode: section.area_code,
        subtest_code: section.subtest_code,
        test_instrument_subtest_id: section.test_instrument_subtest_id,
        itemNumber: section.startQ,
        optionLabels: labels,
        pointsMm: existingPoints,
        bubbleDiameterMm: section.bubbleDiameterMm,
        minBubbleMm: section.minBubbleMm ?? 2.5,
    });
}

/**
 * Resize the circle diameter of a solidified item concentrically around its option centers.
 */
export function resizeSolidItemBubbleDiameter(
    section: PlottedItemSection,
    newDiameterMm: number,
    minBubbleMm?: number,
): PlottedItemSection {
    const labels = (section.customValuesStr || 'A, B, C, D')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    const pointsMm =
        Array.isArray(section.optionPointsMm) &&
        section.optionPointsMm.length > 0
            ? section.optionPointsMm.map((pt) => ({ x: pt.x_mm, y: pt.y_mm }))
            : labels.map((_, idx) => ({
                  x:
                      Number(section.posX) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.x +
                      Number(section.bubbleDiameterMm) / 2 +
                      idx * Number(section.bubblePitchMm),
                  y:
                      Number(section.posY) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.y +
                      Number(section.bubbleDiameterMm) / 2,
              }));

    const minBubbleDiameter =
        minBubbleMm ?? section.minBubbleMm ?? MIN_BUBBLE_MM;

    return buildSolidItemFromOptionPoints({
        id: section.id,
        pageNo: section.page_no,
        areaCode: section.area_code,
        subtest_code: section.subtest_code,
        test_instrument_subtest_id: section.test_instrument_subtest_id,
        itemNumber: section.startQ,
        optionLabels: labels,
        pointsMm,
        bubbleDiameterMm: newDiameterMm,
        minBubbleMm: minBubbleDiameter,
        rotationDeg: section.rotationDeg,
    });
}

/**
 * Clone a solidified item at a new Option-A center anchor (x, y) with a new identity,
 * target aptitude area, and auto-incremented item number.
 */
export function cloneSolidItemAtAnchor(
    source: PlottedItemSection,
    anchorFirstOptionMm: { x: number; y: number },
    options: {
        id?: string;
        pageNo?: number;
        areaCode?: string;
        subtest_code?: string | null;
        test_instrument_subtest_id?: number | string | null;
        itemNumber: number;
    },
): PlottedItemSection {
    const labels = (source.customValuesStr || 'A, B, C, D')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    const sourcePoints =
        Array.isArray(source.optionPointsMm) &&
        source.optionPointsMm.length === labels.length
            ? source.optionPointsMm.map((pt) => ({ x: pt.x_mm, y: pt.y_mm }))
            : labels.map((_, idx) => ({
                  x:
                      Number(source.posX) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.x +
                      Number(source.bubbleDiameterMm) / 2 +
                      idx * Number(source.bubblePitchMm),
                  y:
                      Number(source.posY) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.y +
                      Number(source.bubbleDiameterMm) / 2,
              }));

    const base0 = sourcePoints[0] ?? { x: 30, y: 60 };
    const translatedPoints = sourcePoints.map((pt) => ({
        x: anchorFirstOptionMm.x + (pt.x - base0.x),
        y: anchorFirstOptionMm.y + (pt.y - base0.y),
    }));

    return buildSolidItemFromOptionPoints({
        id: options.id,
        pageNo: options.pageNo ?? source.page_no ?? 1,
        areaCode: options.areaCode ?? source.area_code,
        subtest_code: options.subtest_code ?? source.subtest_code,
        test_instrument_subtest_id:
            options.test_instrument_subtest_id ??
            source.test_instrument_subtest_id,
        itemNumber: options.itemNumber,
        optionLabels: labels,
        pointsMm: translatedPoints,
        bubbleDiameterMm: source.bubbleDiameterMm,
        minBubbleMm: source.minBubbleMm ?? 2.5,
    });
}

/**
 * Options for multiplying a solidified item downward with exact millimeter pitch.
 */
export interface MultiplyItemsDownwardOptions {
    count: number;
    distancePerItemMm: number;
    startItemNumber?: number;
    areaCode?: string;
    pageNo?: number;
    direction?: 'vertical' | 'horizontal';
    slantAngleDeg?: number;
}

/**
 * Detect the vertical step distance (in mm) between consecutive items in the same area and page.
 */
export function detectAdjacentVerticalStepMm(
    sections: Array<Record<string, unknown>>,
    targetArea: string,
    pageNo: number = 1,
): number | null {
    const normArea = normalizeOmrAreaCode(targetArea);
    const areaItems = (sections || [])
        .filter(
            (s) =>
                s &&
                s.type !== 'roll' &&
                Number(s.page_no || 1) === Number(pageNo || 1) &&
                normalizeOmrAreaCode(String(s.area_code || s.aptitudeAreaCode || '')) === normArea,
        )
        .map((s) => ({
            itemNum: Number(s.startQ) || 1,
            y: Number(s.posY) || 0,
        }))
        .sort((a, b) => a.itemNum - b.itemNum);

    if (areaItems.length >= 2) {
        for (let i = 0; i < areaItems.length - 1; i++) {
            if (areaItems[i + 1].itemNum === areaItems[i].itemNum + 1) {
                const diff = Number((areaItems[i + 1].y - areaItems[i].y).toFixed(2));
                if (diff > 1.0 && diff < 60.0) {
                    return diff;
                }
            }
        }
    }
    return null;
}

/**
 * Multiply a solidified item downwards with an exact vertical pitch per item,
 * auto-incrementing item numbers and preserving horizontal alignment.
 */
export function multiplyItemsDownward(
    source: PlottedItemSection,
    options: MultiplyItemsDownwardOptions,
    existingSections: Array<Record<string, unknown>> = [],
): PlottedItemSection[] {
    const count = Math.max(1, Math.min(100, Math.round(Number(options.count) || 1)));
    const distancePerItemMm = Number(options.distancePerItemMm) || Number(source.rowPitchMm) || 6.8;
    const pageNo = Number(options.pageNo ?? source.page_no) || 1;
    const targetArea = normalizeOmrAreaCode(options.areaCode ?? source.area_code);

    const labels = (source.customValuesStr || 'A, B, C, D')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

    const sourcePoints =
        Array.isArray(source.optionPointsMm) &&
        source.optionPointsMm.length === labels.length
            ? source.optionPointsMm.map((pt) => ({ x: pt.x_mm, y: pt.y_mm }))
            : labels.map((_, idx) => ({
                  x:
                      Number(source.posX) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.x +
                      Number(source.bubbleDiameterMm) / 2 +
                      idx * Number(source.bubblePitchMm),
                  y:
                      Number(source.posY) +
                      GRID_BUBBLE_ORIGIN_OFFSET_MM.y +
                      Number(source.bubbleDiameterMm) / 2,
              }));

    const result: PlottedItemSection[] = [];
    const combined = [...existingSections];

    for (let k = 1; k <= count; k++) {
        const nextQ =
            options.startItemNumber != null
                ? options.startItemNumber + (k - 1)
                : nextItemNumberForArea(combined, targetArea, source.subtest_code as string);

        const newId = `${targetArea}_P${pageNo}_Q${nextQ}_${existingSections.length + k}`;
        const isHorizontal = options.direction === 'horizontal';
        const slantDeg = Number(options.slantAngleDeg) || 0;
        const slantRad = (slantDeg * Math.PI) / 180;
        const cosSlant = Math.cos(slantRad);
        const sinSlant = Math.sin(slantRad);

        const stepDX = isHorizontal
            ? distancePerItemMm * cosSlant
            : distancePerItemMm * sinSlant;
        const stepDY = isHorizontal
            ? distancePerItemMm * sinSlant
            : distancePerItemMm * cosSlant;

        const anchor0 = sourcePoints[0] ?? {
            x: Number(source.posX),
            y: Number(source.posY),
        };
        const newAnchor = {
            x: anchor0.x + k * stepDX,
            y: anchor0.y + k * stepDY,
        };

        const translatedPoints = sourcePoints.map((pt) => {
            const relX = pt.x - anchor0.x;
            const relY = pt.y - anchor0.y;
            const rotX = relX * cosSlant - relY * sinSlant;
            const rotY = relX * sinSlant + relY * cosSlant;
            return {
                x: Number((newAnchor.x + rotX).toFixed(2)),
                y: Number((newAnchor.y + rotY).toFixed(2)),
            };
        });

        const newItem = buildSolidItemFromOptionPoints({
            id: newId,
            pageNo,
            areaCode: targetArea,
            subtest_code: source.subtest_code,
            test_instrument_subtest_id: source.test_instrument_subtest_id,
            itemNumber: nextQ,
            optionLabels: labels,
            pointsMm: translatedPoints,
            bubbleDiameterMm: source.bubbleDiameterMm,
            minBubbleMm: source.minBubbleMm ?? 2.5,
        });

        if (isHorizontal) {
            newItem.bubblePitchMm = distancePerItemMm;
        } else {
            newItem.rowPitchMm = distancePerItemMm;
        }
        result.push(newItem);
        combined.push(newItem);
    }

    return result;
}

/**
 * Rotate a single solidified item's bubbles around an anchor or center by angleDeg.
 */
type ExactOptionPoint = {
    label: string;
    x_mm: number;
    y_mm: number;
};

type SectionWithExactOptionPoints = {
    exactOptionPointsMm?: ExactOptionPoint[];
    optionLabels?: unknown[];
    customValuesStr?: unknown;
    optionsCount?: unknown;
    optionPointsMm?: unknown;
    rotationDeg?: unknown;
};

export function rotateSolidItem(
    section: PlottedItemSection,
    angleDeg: number,
    centerPointMm?: { x: number; y: number },
): PlottedItemSection {
    if (Number(angleDeg) === 0) return { ...section };
    const configuredLabels = (
        section as PlottedItemSection & { optionLabels?: string[] }
    ).optionLabels;
    const labels = Array.isArray(configuredLabels) && configuredLabels.length > 0
        ? configuredLabels.map(String)
        : (section.customValuesStr || 'A, B, C, D')
              .split(',')
              .map((label) => label.trim())
              .filter(Boolean);
    const questionsCount = Math.max(1, Number(section.questionsCount) || 1);
    const expectedPointCount = questionsCount * labels.length;
    const exactOptionPointsMm = (
        section as PlottedItemSection & SectionWithExactOptionPoints
    ).exactOptionPointsMm;
    const hasUnchangedExplicitPoints =
        Array.isArray(exactOptionPointsMm) &&
        exactOptionPointsMm.length === expectedPointCount &&
        Array.isArray(section.optionPointsMm) &&
        section.optionPointsMm.length === expectedPointCount &&
        exactOptionPointsMm.every(
            (point, index) =>
                Number(point.x_mm.toFixed(3)) === Number(section.optionPointsMm[index].x_mm) &&
                Number(point.y_mm.toFixed(3)) === Number(section.optionPointsMm[index].y_mm),
        );
    const sourcePoints = hasUnchangedExplicitPoints
        ? exactOptionPointsMm.map((point) => ({
              label: point.label,
              x: Number(point.x_mm),
              y: Number(point.y_mm),
          }))
        : Array.isArray(section.optionPointsMm) &&
            section.optionPointsMm.length === expectedPointCount
          ? section.optionPointsMm.map((point) => ({
                label: point.label,
                x: Number(point.x_mm),
                y: Number(point.y_mm),
            }))
          : Array.from({ length: expectedPointCount }, (_, index) => {
                const questionIndex = Math.floor(index / labels.length);
                const optionIndex = index % labels.length;
                return {
                    label: labels[optionIndex],
                    x:
                        Number(section.posX) +
                        GRID_BUBBLE_ORIGIN_OFFSET_MM.x +
                        Number(section.bubbleDiameterMm) / 2 +
                        optionIndex * Number(section.bubblePitchMm),
                    y:
                        Number(section.posY) +
                        GRID_BUBBLE_ORIGIN_OFFSET_MM.y +
                        Number(section.bubbleDiameterMm) / 2 +
                        questionIndex * Number(section.rowPitchMm),
                };
            });
    const origin = centerPointMm ?? sourcePoints[0];
    const radians = (Number(angleDeg) * Math.PI) / 180;
    const cosine = Math.cos(radians);
    const sine = Math.sin(radians);
    const exactRotatedPoints: ExactOptionPoint[] = sourcePoints.map((point) => ({
        label: point.label,
        x_mm: origin.x + (point.x - origin.x) * cosine - (point.y - origin.y) * sine,
        y_mm: origin.y + (point.x - origin.x) * sine + (point.y - origin.y) * cosine,
    }));
    const rotatedPoints = exactRotatedPoints.map((point) => ({
        label: point.label,
        x_mm: Number(point.x_mm.toFixed(3)),
        y_mm: Number(point.y_mm.toFixed(3)),
    }));
    const radius = Number(section.bubbleDiameterMm) / 2;
    const minX = Math.min(...rotatedPoints.map((point) => point.x_mm)) - radius;
    const minY = Math.min(...rotatedPoints.map((point) => point.y_mm)) - radius;
    const maxX = Math.max(...rotatedPoints.map((point) => point.x_mm)) + radius;
    const maxY = Math.max(...rotatedPoints.map((point) => point.y_mm)) + radius;
    const result = {
        ...section,
        optionPointsMm: rotatedPoints,
        posX: Number(minX.toFixed(3)),
        posY: Number(minY.toFixed(3)),
        w_mm: Number((maxX - minX).toFixed(3)),
        h_mm: Number((maxY - minY).toFixed(3)),
        rotationDeg: Number(((Number(section.rotationDeg) || 0) + Number(angleDeg)).toFixed(2)),
    };
    Object.defineProperty(result, 'exactOptionPointsMm', {
        value: exactRotatedPoints,
        enumerable: false,
        configurable: true,
    });
    return result;
}

export function setSectionAbsoluteTiltDeg<T extends Record<string, unknown>>(
    section: T,
    targetAngleDeg: number,
): T {
    const clampedTarget = Math.max(-15, Math.min(15, Number(targetAngleDeg) || 0));
    const currentSection = section as T & SectionWithExactOptionPoints;
    let currentAngle = Number.isFinite(Number(currentSection.rotationDeg))
        ? Number(currentSection.rotationDeg)
        : 0;
    const explicitPoints = Array.isArray(currentSection.optionPointsMm)
        ? (currentSection.optionPointsMm as ExactOptionPoint[])
        : null;
    if (explicitPoints && explicitPoints.length > 1) {
        const configuredLabels = Array.isArray(currentSection.optionLabels) &&
            currentSection.optionLabels.length >= 2
            ? currentSection.optionLabels
            : typeof currentSection.customValuesStr === 'string'
              ? currentSection.customValuesStr
                    .split(',')
                    .map((label) => label.trim())
                    .filter(Boolean)
              : [];
        const labelsCount = Math.max(
            2,
            configuredLabels.length >= 2
                ? configuredLabels.length
                : Number(currentSection.optionsCount) || 4,
        );
        const exactPoints =
            Array.isArray(currentSection.exactOptionPointsMm) &&
            currentSection.exactOptionPointsMm.length === explicitPoints.length &&
            currentSection.exactOptionPointsMm.every(
                (point, index) =>
                    Number(point.x_mm.toFixed(3)) === Number(explicitPoints[index].x_mm) &&
                    Number(point.y_mm.toFixed(3)) === Number(explicitPoints[index].y_mm),
            )
                ? currentSection.exactOptionPointsMm
                : explicitPoints;
        const firstRow = Array.isArray(exactPoints[0])
            ? (exactPoints[0] as unknown as ExactOptionPoint[])
            : exactPoints.slice(0, Math.min(exactPoints.length, labelsCount));
        if (firstRow.length >= 2) {
            const firstPoint = firstRow[0];
            const lastPoint = firstRow[firstRow.length - 1];
            const dx = Number(lastPoint.x_mm) - Number(firstPoint.x_mm);
            const dy = Number(lastPoint.y_mm) - Number(firstPoint.y_mm);
            if (Math.hypot(dx, dy) > 0.01) {
                const measuredAngle = (Math.atan2(dy, dx) * 180) / Math.PI;
                if (
                    !Number.isFinite(Number(currentSection.rotationDeg)) ||
                    Math.abs(measuredAngle - currentAngle) > 0.05
                ) {
                    currentAngle = measuredAngle;
                }
            }
        }
    }
    const delta = clampedTarget - currentAngle;
    const rotated = rotateSolidItem(
        section as unknown as PlottedItemSection,
        delta,
    ) as PlottedItemSection & SectionWithExactOptionPoints;
    const result = {
        ...rotated,
        rotationDeg: Number(clampedTarget.toFixed(2)),
    } as unknown as T;
    if (Array.isArray(rotated.exactOptionPointsMm)) {
        Object.defineProperty(result, 'exactOptionPointsMm', {
            value: rotated.exactOptionPointsMm,
            enumerable: false,
            configurable: true,
        });
    }
    return result;
}

/**
 * Rotate a group of solidified items around the group's bounding center.
 */
export function rotateGroupOfItems(
    sections: PlottedItemSection[],
    indices: number[],
    angleDeg: number,
): PlottedItemSection[] {
    if (!Array.isArray(indices) || indices.length === 0 || angleDeg === 0) {
        return sections;
    }

    const targetSections = indices
        .map((idx) => sections[idx])
        .filter((sec): sec is PlottedItemSection => Boolean(sec && sec.type !== 'roll'));

    if (targetSections.length === 0) return sections;

    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;

    for (const sec of targetSections) {
        const pts = sec.optionPointsMm || [];
        for (const pt of pts) {
            if (pt.x_mm < minX) minX = pt.x_mm;
            if (pt.x_mm > maxX) maxX = pt.x_mm;
            if (pt.y_mm < minY) minY = pt.y_mm;
            if (pt.y_mm > maxY) maxY = pt.y_mm;
        }
    }

    if (!Number.isFinite(minX)) return sections;

    const groupCenter = {
        x: (minX + maxX) / 2,
        y: (minY + maxY) / 2,
    };

    const targetSet = new Set(indices);
    return sections.map((sec, idx) => {
        if (targetSet.has(idx) && sec.type !== 'roll') {
            return rotateSolidItem(sec, angleDeg, groupCenter);
        }
        return sec;
    });
}

/**
 * Interpolate evenly spaced option bubble points (e.g. 'A', 'B', 'C', 'D')
 * between a clicked first option bubble (e.g. 'A') and last option bubble (e.g. 'D').
 */
export function interpolateOptionPointsFromSpan(
    firstPoint: { x: number; y: number },
    lastPoint: { x: number; y: number },
    optionLabels: string[] = ['A', 'B', 'C', 'D'],
): Array<{ label: string; x: number; y: number }> {
    const labels =
        Array.isArray(optionLabels) && optionLabels.length >= 2
            ? optionLabels
            : ['A', 'B', 'C', 'D'];
    const intervals = labels.length - 1;
    const dX = (Number(lastPoint.x) - Number(firstPoint.x)) / intervals;
    const dY = (Number(lastPoint.y) - Number(firstPoint.y)) / intervals;

    return labels.map((lbl, i) => ({
        label: lbl,
        x: Number((Number(firstPoint.x) + i * dX).toFixed(2)),
        y: Number((Number(firstPoint.y) + i * dY).toFixed(2)),
    }));
}

/**
 * Interpolate 10 evenly spaced digit bubble points (values '0' through '9')
 * between a clicked first bubble ('0') and last bubble ('9').
 */
export function interpolateTenDigitPointsFromSpan(
    firstPoint: { x: number; y: number },
    lastPoint: { x: number; y: number },
): Array<{ label: string; x: number; y: number }> {
    const dX = (Number(lastPoint.x) - Number(firstPoint.x)) / 9;
    const dY = (Number(lastPoint.y) - Number(firstPoint.y)) / 9;
    const points: Array<{ label: string; x: number; y: number }> = [];

    for (let i = 0; i <= 9; i++) {
        points.push({
            label: String(i),
            x: Number((Number(firstPoint.x) + i * dX).toFixed(2)),
            y: Number((Number(firstPoint.y) + i * dY).toFixed(2)),
        });
    }

    return points;
}

export interface BuildApplicantDigitRollOptions {
    id?: string;
    pageNo?: number;
    digitCount?: number;
    pointsMm: Array<{ x: number; y: number; label?: string }>;
    bubbleDiameterMm?: number;
    rollDirection?: 'vertical' | 'horizontal';
    stepPitchMm?: number;
}

/**
 * Build a valid OMRChecker 'roll' identity section from plotted digit bubble points.
 * Automatically infers vertical column vs horizontal row orientation from point span.
 */
export function buildApplicantDigitRollFromPoints(
    options: BuildApplicantDigitRollOptions,
): Record<string, unknown> {
    const points = options.pointsMm || [];
    const p0 = points[0] ?? { x: 50, y: 30 };
    const p9 = points[points.length - 1] ?? { x: 50, y: 73.2 };

    const spanX = Math.abs(Number(p9.x) - Number(p0.x));
    const spanY = Math.abs(Number(p9.y) - Number(p0.y));

    const rollDirection: 'vertical' | 'horizontal' =
        options.rollDirection ?? (spanY >= spanX ? 'vertical' : 'horizontal');

    const bubbleDiameterMm = Number(options.bubbleDiameterMm) || 4.5;
    const digitCount = Math.max(2, Math.min(10, Number(options.digitCount) || 6));
    const pageNo = Number(options.pageNo) || 1;
    const id = options.id || `Applicant_Number_Page_${pageNo}`;

    let bubblePitchMm = 5.5;
    let rowPitchMm = 4.8;
    let posX = Math.min(Number(p0.x), Number(p9.x));
    let posY = Math.min(Number(p0.y), Number(p9.y));

    if (rollDirection === 'vertical') {
        rowPitchMm = Number((spanY / 9).toFixed(2)) || 4.8;
        bubblePitchMm = Number(options.stepPitchMm) || 5.5;
    } else {
        bubblePitchMm = Number((spanX / 9).toFixed(2)) || 5.5;
        rowPitchMm = Number(options.stepPitchMm) || 4.8;
    }
    const rollOffset = ROLL_BUBBLE_ORIGIN_OFFSET_MM;
    posX = Number(
        (
            Number(p0.x) -
            bubbleDiameterMm / 2 -
            rollOffset.x
        ).toFixed(2),
    );
    posY = Number(
        (
            Number(p0.y) -
            bubbleDiameterMm / 2 -
            rollOffset.y
        ).toFixed(2),
    );

    const cols = rollDirection === 'horizontal' ? 10 : digitCount;
    const rows = rollDirection === 'horizontal' ? digitCount : 10;
    const w_mm = Number((bubbleDiameterMm + (cols - 1) * bubblePitchMm).toFixed(2));
    const h_mm = Number((bubbleDiameterMm + (rows - 1) * rowPitchMm).toFixed(2));

    return {
        id,
        name: `Applicant Number · Page ${pageNo}`,
        type: 'roll',
        identity_grid: true,
        page_no: pageNo,
        rollDirection,
        questionsCount: digitCount,
        posX,
        posY,
        w_mm,
        h_mm,
        bubbleDiameterMm,
        bubblePitchMm,
        rowPitchMm,
        isLocked: false,
    };
}

export interface RemappedPageDeclarations {
    inventory: Array<Record<string, unknown>>;
    identity: UploadedIdentityDeclaration;
}

/** Rebind page-scoped declarations by page identity, dropping unmapped pages. */
export function remapPageDeclarations(
    inventory: Array<Record<string, unknown>>,
    identity: UploadedIdentityDeclaration,
    pageNumberMap: ReadonlyMap<number, number>,
): RemappedPageDeclarations {
    const remap = (declaration: Record<string, unknown>) => {
        const oldPageNo = Number(declaration.page_no);
        const newPageNo = pageNumberMap.get(oldPageNo);
        return Number.isFinite(oldPageNo) && newPageNo !== undefined
            ? { ...declaration, page_no: newPageNo }
            : null;
    };
    const regions = Array.isArray(identity.regions)
        ? identity.regions.flatMap((region) => {
              const oldPageNo = Number(region.page_no);
              const newPageNo = pageNumberMap.get(oldPageNo);
              return Number.isFinite(oldPageNo) && newPageNo !== undefined
                  ? [{ ...region, page_no: newPageNo }]
                  : [];
          })
        : undefined;
    const writeInBoxes = Array.isArray(identity.write_in_boxes)
        ? identity.write_in_boxes.flatMap((box) => {
              const oldPageNo = Number(box.page_no);
              const newPageNo = pageNumberMap.get(oldPageNo);
              return Number.isFinite(oldPageNo) && newPageNo !== undefined
                  ? [{ ...box, page_no: newPageNo }]
                  : [];
          })
        : undefined;
    const identityPages = Array.isArray(identity.identity_pages)
        ? identity.identity_pages
              .flatMap((pageNo) => {
                  const oldPageNo = Number(pageNo);
                  const newPageNo = pageNumberMap.get(oldPageNo);
                  return Number.isFinite(oldPageNo) && newPageNo !== undefined
                      ? [newPageNo]
                      : [];
              })
              .filter((pageNo, index, list) => list.indexOf(pageNo) === index)
        : undefined;

    return {
        inventory: inventory
            .map(remap)
            .filter(
                (declaration): declaration is Record<string, unknown> =>
                    declaration !== null,
            ),
        identity: {
            ...identity,
            ...(regions === undefined ? {} : { regions }),
            ...(writeInBoxes === undefined ? {} : { write_in_boxes: writeInBoxes }),
            ...(identityPages === undefined
                ? {}
                : { identity_pages: identityPages }),
        },
    };
}

/**
 * Automatically derive and synchronize declaredInventory from the plotted item sections
 * so uploaded-sheet validation passes without manual inventory bookkeeping.
 */
export function syncDeclaredInventoryFromSections(
    sections: Array<Record<string, unknown>>,
    existingInventory: Array<Record<string, unknown>> = [],
): Array<{
    id: string;
    page_no: number;
    area_code: string;
    question_count: number;
    option_labels: string[];
    geometry_id?: string;
}> {
    const grouped = new Map<
        string,
        {
            page_no: number;
            area_code: string;
            question_count: number;
            option_labels: string[];
            geometry_id?: string;
        }
    >();

    for (const sec of sections) {
        if (!sec || sec.type === 'roll' || sec.identity_grid) continue;
        const pageNo = Math.max(1, Number(sec.page_no) || 1);
        const areaCode = normalizeOmrAreaCode(sec.area_code as string);
        const qCount = Math.max(1, Number(sec.questionsCount) || 1);
        const customParsed =
            typeof sec.customValuesStr === 'string' &&
            sec.customValuesStr.trim() !== ''
                ? sec.customValuesStr
                      .split(',')
                      .map((s) => s.trim())
                      .filter(Boolean)
                : null;
        const pointLabels =
            Array.isArray(sec.optionPointsMm) && sec.optionPointsMm.length >= 2
                ? sec.optionPointsMm
                      .map((p: unknown) =>
                          typeof p === 'object' && p !== null && 'label' in p && typeof p.label === 'string'
                              ? p.label.trim()
                              : '',
                      )
                      .filter(Boolean)
                : null;
        const explicitOptionLabels = [
            sec.optionLabels,
            sec.bubble_values,
            sec.option_labels,
            customParsed,
            pointLabels && pointLabels.length >= 2 ? pointLabels : null,
        ].find(
            (labels): labels is string[] =>
                Array.isArray(labels) &&
                labels.length >= 2 &&
                labels.length <= 10 &&
                labels.every((label) => typeof label === 'string'),
        );
        const optCount =
            Number(sec.optionsCount) ||
            (explicitOptionLabels ? explicitOptionLabels.length : 4);
        const optLabels =
            explicitOptionLabels ??
            (optCount === 5
                ? ['A', 'B', 'C', 'D', 'E']
                : optCount === 2
                  ? ['A', 'B']
                  : ['A', 'B', 'C', 'D']);
        const key = `${pageNo}:${areaCode}`;
        const prev = grouped.get(key);
        if (prev) {
            prev.question_count += qCount;
        } else {
            grouped.set(key, {
                page_no: pageNo,
                area_code: areaCode,
                question_count: qCount,
                option_labels: optLabels,
                geometry_id: String(sec.id ?? ''),
            });
        }
    }

    if (grouped.size === 0) {
        return existingInventory as Array<{
            id: string;
            page_no: number;
            area_code: string;
            question_count: number;
            option_labels: string[];
            geometry_id?: string;
        }>;
    }

    const result: Array<{
        id: string;
        page_no: number;
        area_code: string;
        question_count: number;
        option_labels: string[];
        geometry_id?: string;
    }> = [];

    let idx = 0;
    for (const [, entry] of grouped) {
        idx += 1;
        const existingMatch = existingInventory.find(
            (item) =>
                Number(item?.page_no) === entry.page_no &&
                normalizeOmrAreaCode(item?.area_code as string) ===
                    entry.area_code,
        );
        result.push({
            id:
                (existingMatch?.id as string) ||
                `inventory-${entry.page_no}-${idx}`,
            page_no: entry.page_no,
            area_code: entry.area_code,
            question_count: entry.question_count,
            option_labels: entry.option_labels,
            geometry_id: entry.geometry_id,
        });
    }
    return result;
}
