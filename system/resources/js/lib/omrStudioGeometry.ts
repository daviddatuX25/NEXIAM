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
export const MIN_OPTION_GAP_MM = 1.26;
export const ALLOWED_INSET_RANGE_MM: [number, number] = [3.0, 40.0];

export interface PaperDefinition {
  name: string;
  width_mm: number;
  height_mm: number;
  default_margin_mm: number;
}

export const STANDARD_PAPERS: Record<string, PaperDefinition> = {
  a4: { name: 'A4', width_mm: 210, height_mm: 297, default_margin_mm: 15 },
  legal: { name: 'Legal (Long Bond)', width_mm: 216, height_mm: 330, default_margin_mm: 15 },
  letter: { name: 'Letter (Short Bond)', width_mm: 216, height_mm: 279, default_margin_mm: 15 },
};

export function mmToPx(mm: number): number {
  return Math.round(mm * PX_PER_MM);
}

export function pxToMm(px: number): number {
  return Number((px / PX_PER_MM).toFixed(2));
}

export interface InsetMargins {
  top_mm: number;
  right_mm: number;
  bottom_mm: number;
  left_mm: number;
}

export interface StudioBlock {
  id: string;
  type: 'mcq' | 'tf' | 'likert' | 'custom' | 'roll' | 'qr' | 'text' | 'header' | 'signature' | 'instructions' | 'metadata_box' | 'custom_text';
  x_mm: number;
  y_mm: number;
  w_mm: number;
  h_mm: number;
  // Question configuration
  questions?: number;
  cols?: number;
  options?: number;
  start_q?: number;
  custom_values?: string[];
  bubble_pitch_mm?: number;
  row_pitch_mm?: number;
  // Roll specific
  digits?: number;
  // Text / Overlay specific
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
  direction?: string;
  origin_x: number;
  origin_y: number;
  width: number;
  height: number;
  option_pitch_px: number;
  row_pitch_px: number;
  bubble_width_px: number;
  bubble_height_px: number;
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

/**
 * Emit native fieldBlocks mapping for OMRChecker validation and compilation
 */
export function emitNativeMapping(
  pageWidthMm: number,
  pageHeightMm: number,
  blocks: StudioBlock[]
): NativeMappingPayload {
  const nativeBlocks: NativeMappingBlock[] = [];
  const bubbleSizePx = mmToPx(4.5);

  for (const block of blocks) {
    if (['mcq', 'tf', 'likert', 'custom'].includes(block.type)) {
      const qs = Math.max(1, block.questions ?? 10);
      const cols = Math.max(1, block.cols ?? 1);
      let opts = 4;
      if (block.type === 'tf') opts = 2;
      else if (block.type === 'likert') opts = 5;
      else if (block.type === 'custom' && block.custom_values && block.custom_values.length > 0) opts = block.custom_values.length;
      else opts = block.options === 5 ? 5 : block.options === 2 ? 2 : 4;

      const start = Math.max(1, block.start_q ?? 1);
      const rowsPerCol = Math.ceil(qs / cols);

      const customOptionPitch = block.bubble_pitch_mm ? mmToPx(block.bubble_pitch_mm) : null;
      const customRowPitch = block.row_pitch_mm ? mmToPx(block.row_pitch_mm) : null;

      if (cols === 1) {
        const labels = Array.from({ length: qs }, (_, i) => `q${start + i}`);
        const blockW = mmToPx(block.w_mm);
        const blockH = mmToPx(block.h_mm);
        const optionPitch = customOptionPitch ?? Math.max(bubbleSizePx + 15, Math.round(blockW / opts));
        const rowPitch = customRowPitch ?? Math.max(bubbleSizePx + 15, Math.round(blockH / rowsPerCol));

        nativeBlocks.push({
          name: block.id,
          page_no: 1,
          field_type: opts === 5 ? 'QTYPE_MCQ5' : 'QTYPE_MCQ4',
          label_prefix: 'q',
          labels,
          rows: rowsPerCol,
          options: opts,
          origin_x: mmToPx(block.x_mm),
          origin_y: mmToPx(block.y_mm),
          width: blockW,
          height: blockH,
          option_pitch_px: optionPitch,
          row_pitch_px: rowPitch,
          bubble_width_px: bubbleSizePx,
          bubble_height_px: bubbleSizePx,
        });
      } else {
        // Multi-column MCQ: emit distinct sub-blocks for OMRChecker
        const colWidthMm = block.w_mm / cols;
        for (let c = 0; c < cols; c++) {
          const colStart = start + c * rowsPerCol;
          const colCount = Math.min(rowsPerCol, qs - c * rowsPerCol);
          if (colCount <= 0) break;

          const labels = Array.from({ length: colCount }, (_, i) => `q${colStart + i}`);
          const colX = block.x_mm + c * colWidthMm;
          const blockW = mmToPx(colWidthMm);
          const blockH = mmToPx(block.h_mm);
          const optionPitch = customOptionPitch ?? Math.max(bubbleSizePx + 15, Math.round(blockW / opts));
          const rowPitch = customRowPitch ?? Math.max(bubbleSizePx + 15, Math.round(blockH / rowsPerCol));

          nativeBlocks.push({
            name: `${block.id}_col${c + 1}`,
            page_no: 1,
            field_type: opts === 5 ? 'QTYPE_MCQ5' : 'QTYPE_MCQ4',
            label_prefix: 'q',
            labels,
            rows: colCount,
            options: opts,
            origin_x: mmToPx(colX),
            origin_y: mmToPx(block.y_mm),
            width: blockW,
            height: blockH,
            option_pitch_px: optionPitch,
            row_pitch_px: rowPitch,
            bubble_width_px: bubbleSizePx,
            bubble_height_px: bubbleSizePx,
          });
        }
      }
    } else if (block.type === 'roll') {
      const digits = Math.max(2, Math.min(10, block.digits ?? 6));
      const labels = Array.from({ length: digits }, (_, i) => `${block.id.toLowerCase()}_${i + 1}`);
      const blockW = mmToPx(block.w_mm);
      const blockH = mmToPx(block.h_mm);
      const minPitch = bubbleSizePx + Math.round(MIN_OPTION_GAP_MM * PX_PER_MM);
      const colPitch = Math.max(minPitch, Math.round(blockW / digits));
      const rowPitch = Math.max(minPitch, Math.round(blockH / 10));

      nativeBlocks.push({
        name: block.id,
        page_no: 1,
        field_type: 'QTYPE_INT',
        label_prefix: `${block.id.toLowerCase()}_`,
        labels,
        rows: 10,
        options: 10,
        direction: 'vertical',
        origin_x: mmToPx(block.x_mm),
        origin_y: mmToPx(block.y_mm),
        width: blockW,
        height: blockH,
        option_pitch_px: rowPitch,
        row_pitch_px: colPitch,
        bubble_width_px: bubbleSizePx,
        bubble_height_px: bubbleSizePx,
      });
    }
  }

  return {
    page_width: mmToPx(pageWidthMm),
    page_height: mmToPx(pageHeightMm),
    page_count: 1,
    blocks: nativeBlocks,
  };
}

/**
 * Emit fixed overlay boxes (QR code, text labels, signature areas, metadata boxes)
 */
export function emitOverlayBoxes(
  blocks: StudioBlock[],
  _pageWidthMm: number,
  _pageHeightMm: number
): { boxes: OverlayBox[] } {
  const boxes: OverlayBox[] = [];

  for (const block of blocks) {
    if (block.type === 'qr') {
      boxes.push({
        name: block.id,
        page_no: 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: block.tag ?? 'blank_instance_qr',
        scope: block.scope ?? 'blank_instance',
        overflow: 'truncate',
        content: null,
      });
    } else if (block.type === 'header' || (block.type === 'text' && block.tag === 'header_title')) {
      boxes.push({
        name: block.id,
        page_no: 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: 'header_title',
        scope: 'template',
        overflow: block.overflow ?? 'shrink',
        font_size: block.font_size ?? 14.0,
        content: block.content ?? block.title ?? 'EXAMINATION ANSWER SHEET',
      });
    } else if (block.type === 'instructions' || block.tag === 'instructions_box' || block.tag === 'instruction_box') {
      boxes.push({
        name: block.id,
        page_no: 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: 'instructions_box',
        scope: 'template',
        overflow: block.overflow ?? 'wrap',
        font_size: block.font_size ?? 10.0,
        content: block.content ?? block.title ?? 'INSTRUCTIONS: USE 2B PENCIL ONLY',
      });
    } else if (block.type === 'signature') {
      boxes.push({
        name: block.id,
        page_no: 1,
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
        page_no: 1,
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
        page_no: 1,
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
  blocks: StudioBlock[]
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
    if (b.x_mm < m || b.y_mm < m || b.x_mm + b.w_mm > paper.width_mm - m || b.y_mm + b.h_mm > paper.height_mm - m) {
      warnings.push({
        block_id: b.id,
        code: 'margin_breach',
        message: `Block "${b.id}" extends beyond the ${m}mm printable margin.`,
        severity: 'warning',
      });
    }
  }

  // AABB collision detection among blocks
  for (let i = 0; i < blocks.length; i++) {
    for (let j = i + 1; j < blocks.length; j++) {
      const a = blocks[i];
      const b = blocks[j];
      const overlapX = a.x_mm < b.x_mm + b.w_mm && a.x_mm + a.w_mm > b.x_mm;
      const overlapY = a.y_mm < b.y_mm + b.h_mm && a.y_mm + a.h_mm > b.y_mm;
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
