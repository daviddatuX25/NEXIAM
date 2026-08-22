/**
 * Universal OMR Component Suite — atomic sheet components with absolute positioning.
 *
 * Every component owns its own geometry (`posX`/`posY`/`w_mm`/`h_mm` in mm from the
 * sheet edge) and renders independently. `renderFullSheetHtml` emits ONLY the
 * components present — there is no implicit scaffold. Roll + question grids also
 * bridge into OMR section geometry via `componentToSectionData`; text components
 * are HTML-only.
 */

import { contentBlockSizeMm, OMR_BUBBLE_DIAMETER_MM } from './omrStudioGeometry';

export interface OmrTypography {
  fontSizePt: number;
  fontFamily: string;
  fontWeight: 400 | 600 | 700 | 800 | 900;
  textColor: string;
  textAlign: 'left' | 'center' | 'right' | 'justify';
  lineHeight?: number;
  letterSpacingPx?: number;
}

export const DEFAULT_TYPOGRAPHY: OmrTypography = {
  fontSizePt: 12,
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
  fontWeight: 700,
  textColor: '#0f172a',
  textAlign: 'left',
  lineHeight: 1.2,
  letterSpacingPx: 0,
};

export type InputBoxStyle = 'dotted_line' | 'underline' | 'box';
export type InputValueKind = 'text' | 'number' | 'date' | 'multiline';

/** Geometry and appearance shared by every component. */
export interface ComponentGeometry {
  posX: number;
  posY: number;
  w_mm: number;
  h_mm: number;
  isLocked: boolean;
  backgroundColor?: string;
  zIndex?: number;
}

export interface HeaderComponent extends ComponentGeometry {
  id: string;
  type: 'header';
  title: string;
  subtitle: string;
  formCode: string;
  typography: OmrTypography;
}

export interface TextFieldComponent extends ComponentGeometry {
  id: string;
  type: 'candidate_name' | 'birthdate' | 'room_seat';
  label: string;
  inputStyle: InputBoxStyle;
  inputKind: InputValueKind;
  inputBackgroundColor: string;
  typography: OmrTypography;
}

export interface CustomInputComponent extends ComponentGeometry {
  id: string;
  type: 'custom_input';
  label: string;
  inputKind: InputValueKind;
  inputStyle: InputBoxStyle;
  inputBackgroundColor: string;
  typography: OmrTypography;
}

export interface InstructionsComponent extends ComponentGeometry {
  id: string;
  type: 'instructions';
  label: string;
  content: string;
  typography: OmrTypography;
  borderColor: string;
  backgroundColor: string;
}

export interface ExamineeIdMatrixComponent extends ComponentGeometry {
  id: string;
  type: 'roll';
  title: string;
  typography: OmrTypography;
  digitsCount: number;
  bubbleDiameterMm: number;
  bubblePitchMm: number;
  rowPitchMm: number;
}

export interface QuestionGridComponent extends ComponentGeometry {
  id: string;
  name: string;
  type: 'mcq' | 'tf' | 'likert' | 'custom';
  subjectName: string;
  aptitudeAreaCode?: string;
  questionsCount: number;
  cols: number;
  optionsCount: number;
  startQ: number;
  customValuesStr: string;
  bubbleDiameterMm?: number;
  bubblePitchMm: number;
  rowPitchMm: number;
  labelFontSizePt?: number;
  labelStyle: 'column_header' | 'inline_circle';
}

export type OmrComponent =
  | HeaderComponent
  | TextFieldComponent
  | CustomInputComponent
  | InstructionsComponent
  | ExamineeIdMatrixComponent
  | QuestionGridComponent
  | SignatureBoxComponent
  | CustomTextComponent;

export type ComponentKind =
  | 'header'
  | 'candidate_name'
  | 'birthdate'
  | 'room_seat'
  | 'instructions'
  | 'roll'
  | 'question_grid'
  | 'custom_text'
  | 'custom_input'
  | 'signature';

/** Supported authoring paths for the shared component palette. */
export type ComponentPath = 'sheet_designer' | 'upload_document';

export interface ComponentPaletteFilter {
  path: ComponentPath;
  includeTextComponents: boolean;
  includeQuestionComponents: boolean;
  includeOverlayControls: boolean;
}

export const COMPONENT_PATH_FILTERS: Record<ComponentPath, ComponentPaletteFilter> = {
  sheet_designer: {
    path: 'sheet_designer',
    includeTextComponents: true,
    includeQuestionComponents: true,
    includeOverlayControls: true,
  },
  upload_document: {
    path: 'upload_document',
    includeTextComponents: true,
    includeQuestionComponents: false,
    includeOverlayControls: true,
  },
};

export type TextComponentType = 'header' | 'candidate_name' | 'birthdate' | 'room_seat' | 'instructions' | 'custom_text' | 'custom_input' | 'signature';
export type GeometricComponentType = 'roll' | 'mcq' | 'tf' | 'likert' | 'custom';

export function isGeometricComponent(c: OmrComponent): boolean {
  return c.type === 'roll' || c.type === 'mcq' || c.type === 'tf' || c.type === 'likert' || c.type === 'custom';
}

export function isQuestionGridComponent(c: OmrComponent): c is QuestionGridComponent {
  return c.type === 'mcq' || c.type === 'tf' || c.type === 'likert' || c.type === 'custom';
}

export interface SheetSettings {
  paperWidthMm: number;
  paperHeightMm: number;
  marginMm: number;
  markerPattern: 'square' | 'target' | 'aruco';
}
export interface SheetHtmlQrReservation {
  visible: boolean;
  xMm: number;
  yMm: number;
  sizeMm: number;
  overlayTag: 'blank_instance_qr';
}

export interface SheetHtmlDecorations {
  showFiducials: boolean;
  qr: SheetHtmlQrReservation;
}

export const DEFAULT_SHEET_HTML_DECORATIONS: SheetHtmlDecorations = {
  showFiducials: false,
  qr: {
    visible: false,
    xMm: 162,
    yMm: 18,
    sizeMm: 26,
    overlayTag: 'blank_instance_qr',
  },
};

export const DEFAULT_A4_SETTINGS: SheetSettings = {
  paperWidthMm: 210,
  paperHeightMm: 297,
  marginMm: 15,
  markerPattern: 'square',
};

export const CANVAS_PX_PER_MM = 2.476; // 520px sheet width ÷ 210mm — canvas scale authority
export const CANVAS_BUBBLE_DIAMETER_PX = OMR_BUBBLE_DIAMETER_MM * CANVAS_PX_PER_MM;

/** Option labels for a question grid component. */
export function gridOptionLabels(g: QuestionGridComponent): string[] {
  if (g.type === 'tf') return ['T', 'F'];
  if (g.type === 'likert') return ['1', '2', '3', '4', '5'];
  if (g.type === 'custom') return (g.customValuesStr || 'A, B').split(',').map((v) => v.trim()).filter(Boolean);
  const count = Number(g.optionsCount) || 4;
  return ['A', 'B', 'C', 'D', 'E'].slice(0, Math.max(2, Math.min(5, count)));
}

/** Content-derived block width for a question grid. */
export function gridBlockWidthMm(g: QuestionGridComponent): number {
  const opts = gridOptionLabels(g).length;
  const rows = Number(g.questionsCount) || 1;
  return contentBlockSizeMm(opts, rows, Number(g.bubblePitchMm) || 6.0, Number(g.rowPitchMm) || 6.8, Number(g.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM).w_mm;
}

/** Content-derived block height for a question grid. */
export function gridBlockHeightMm(g: QuestionGridComponent): number {
  const opts = gridOptionLabels(g).length;
  const rows = Number(g.questionsCount) || 1;
  return contentBlockSizeMm(opts, rows, Number(g.bubblePitchMm) || 6.0, Number(g.rowPitchMm) || 6.8, Number(g.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM).h_mm;
}
export function rollBlockWidthMm(r: ExamineeIdMatrixComponent): number {
  const digits = Number(r.digitsCount) || 7;
  const bubbleDiameter = Number(r.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
  return Number((bubbleDiameter + (digits - 1) * (Number(r.bubblePitchMm) || 5.5) + 1.5).toFixed(1));
}

/** Content-derived block height in mm for an examinee ID matrix (10 digit rows). */
export function rollBlockHeightMm(r: ExamineeIdMatrixComponent): number {
  const bubbleDiameter = Number(r.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
  return Number((bubbleDiameter + 9 * (Number(r.rowPitchMm) || 4.8) + 1.5).toFixed(1));
}

export const CANVAS_ROLL_PADDING_PX = 3.5;
export const CANVAS_ROLL_MARGIN_PX = 3.5;
export const CANVAS_ROLL_MIN_ROW_GAP_PX = 1.75;
export const CANVAS_ROLL_MIN_BUBBLE_GAP_PX = 1.75;

export function minimumCanvasPitchMm(bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM): number {
  return Number((Math.max(4.2, Number(bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM) + (CANVAS_ROLL_MIN_ROW_GAP_PX / CANVAS_PX_PER_MM)).toFixed(1));
}

/** Row gap used by the Stage-1 matrix renderer and its visual bounds. */
export function canvasRollRowGapPx(rowPitchMm: number, bubbleDiameterPx: number): number {
  return Math.max(CANVAS_ROLL_MIN_ROW_GAP_PX, (Number(rowPitchMm) || 4.8) * CANVAS_PX_PER_MM - bubbleDiameterPx);
}

/** Column gap used by the Stage-1 matrix renderer and its visual bounds. */
export function canvasRollBubbleGapPx(bubblePitchMm: number, bubbleDiameterPx: number): number {
  return Math.max(CANVAS_ROLL_MIN_BUBBLE_GAP_PX, (Number(bubblePitchMm) || 5.5) * CANVAS_PX_PER_MM - bubbleDiameterPx);
}

/** Full Stage-1 matrix width, including label column and padding. */
export function canvasRollBlockWidthMm(r: ExamineeIdMatrixComponent): number {
  const bubbleDiameterPx = Math.max(4.2, Number(r.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM) * CANVAS_PX_PER_MM;
  const labelWidthPx = 3.5 * CANVAS_PX_PER_MM;
  const labelGapPx = 1.5 * CANVAS_PX_PER_MM;
  const bubbleGapPx = canvasRollBubbleGapPx(Number(r.bubblePitchMm) || 5.5, bubbleDiameterPx);
  const digits = Number(r.digitsCount) || 7;
  const widthPx = (CANVAS_ROLL_PADDING_PX * 2)
    + labelWidthPx
    + labelGapPx
    + (digits * bubbleDiameterPx)
    + (Math.max(0, digits - 1) * bubbleGapPx);
  return Number((widthPx / CANVAS_PX_PER_MM).toFixed(1));
}

/** Full Stage-1 matrix footprint, including title, write-in row, padding, and rows. */
export function canvasRollBlockHeightMm(r: ExamineeIdMatrixComponent): number {
  const bubbleDiameterPx = Math.max(4.2, Number(r.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM) * CANVAS_PX_PER_MM;
  const titleLinePx = Math.max(8, (Number(r.typography?.fontSizePt) || 7.5) * 0.9 * 1.5);
  const writeRowPx = bubbleDiameterPx * 1.15;
  const rowGapPx = canvasRollRowGapPx(Number(r.rowPitchMm) || 4.8, bubbleDiameterPx);
  const heightPx = (CANVAS_ROLL_PADDING_PX * 2)
    + titleLinePx
    + CANVAS_ROLL_MARGIN_PX
    + writeRowPx
    + CANVAS_ROLL_MARGIN_PX
    + (10 * bubbleDiameterPx)
    + (9 * rowGapPx);
  return Number((heightPx / CANVAS_PX_PER_MM).toFixed(1));
}

/** Visual bounds for the canvas; text uses its editable size, geometric blocks retain their stored print width. */
export function componentBlockSizeMm(c: OmrComponent): { w_mm: number; h_mm: number } {
  if (c.type === 'roll') {
    return {
      w_mm: Math.max(Number(c.w_mm) || 0, rollBlockWidthMm(c)),
      h_mm: Math.max(Number(c.h_mm) || 0, canvasRollBlockHeightMm(c)),
    };
  }
  if (isQuestionGridComponent(c)) {
    return {
      w_mm: Math.max(Number(c.w_mm) || 0, gridBlockWidthMm(c)),
      h_mm: Math.max(Number(c.h_mm) || 0, gridBlockHeightMm(c)),
    };
  }
  return { w_mm: Number(c.w_mm) || 40, h_mm: Number(c.h_mm) || 12 };
}
export const CANVAS_RENDER_BUBBLE_PX = 10.5;

/** Bounds of the actual compact Stage 3 canvas rows, including labels and padding. */
export function canvasSectionBoundsMm(
  options: number,
  questions: number,
  bubblePitchMm: number,
  rowPitchMm: number,
  isRoll = false,
  bubbleDiameterMm = 4.5,
  renderBubblePx = CANVAS_RENDER_BUBBLE_PX,
): { w_mm: number; h_mm: number } {
  const optionCount = Math.max(2, Number(options) || 4);
  const rowCount = Math.min(50, Math.max(1, Number(questions) || 1));
  const renderBubblePxForMode = isRoll
    ? Math.max(4.2, Number(bubbleDiameterMm) || 4.5) * CANVAS_PX_PER_MM
    : renderBubblePx;
  const minimumPitchMm = minimumCanvasPitchMm(isRoll ? Number(bubbleDiameterMm) || 4.5 : 4.5);
  const bPitch = Math.max(Number(bubblePitchMm) || 6, minimumPitchMm);
  const rPitch = Math.max(Number(rowPitchMm) || 6.8, minimumPitchMm);
  const minimumGapPx = CANVAS_ROLL_MIN_ROW_GAP_PX;
  const bubbleGapPx = Math.max(minimumGapPx, bPitch * CANVAS_PX_PER_MM - renderBubblePxForMode);
  const rowGapPx = Math.max(minimumGapPx, rPitch * CANVAS_PX_PER_MM - renderBubblePxForMode);
  const labelWidthPx = isRoll ? 16 : 18;
  const widthPx = 8 + labelWidthPx + optionCount * renderBubblePxForMode + Math.max(0, optionCount - 1) * bubbleGapPx;
  const heightPx = 8 + rowCount * renderBubblePxForMode + Math.max(0, rowCount - 1) * rowGapPx;

  return {
    w_mm: Number((widthPx / CANVAS_PX_PER_MM).toFixed(1)),
    h_mm: Number((heightPx / CANVAS_PX_PER_MM).toFixed(1)),
  };
}

/** Canvas bounds that hug the rendered bubbles, labels, and row gaps. */
export function canvasComponentBlockSizeMm(c: OmrComponent): { w_mm: number; h_mm: number } {
  if (c.type === 'roll') {
    return {
      w_mm: canvasRollBlockWidthMm(c),
      h_mm: Math.max(Number(c.h_mm) || 0, canvasRollBlockHeightMm(c)),
    };
  }
  if (isQuestionGridComponent(c)) {
    const bubbleDiameterMm = Number(c.bubbleDiameterMm) || 4.5;
    const size = canvasSectionBoundsMm(
      gridOptionLabels(c).length,
      c.questionsCount,
      c.bubblePitchMm,
      c.rowPitchMm,
      false,
      bubbleDiameterMm,
      bubbleDiameterMm * CANVAS_PX_PER_MM,
    );
    return {
      w_mm: Math.max(Number(c.w_mm) || 0, size.w_mm),
      h_mm: Math.max(Number(c.h_mm) || 0, size.h_mm),
    };
  }
  return componentBlockSizeMm(c);
}

/** Escape text for HTML attribute/innerText interpolation. */
function esc(value: string | number | undefined | null): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
export function safeComponentColor(value: unknown, fallback = 'transparent'): string {
  const color = String(value ?? '').trim();
  if (color === 'transparent' || /^#[0-9a-f]{3,8}$/i.test(color)) return color;
  return fallback;
}

export function componentBackgroundColor(component: OmrComponent): string {
  return safeComponentColor(component.backgroundColor);
}

export function componentInputBackgroundColor(component: OmrComponent): string {
  if ('inputBackgroundColor' in component) return safeComponentColor(component.inputBackgroundColor, '#ffffff');
  return '#ffffff';
}

function fieldInputStyleClass(style: InputBoxStyle): string {
  if (style === 'box') return 'omr-field-input-box';
  if (style === 'underline') return 'omr-field-input-underline';
  return 'omr-field-input-dotted';
}

/** Inline CSS style string for a typography object. */
export function typographyToStyleString(t?: OmrTypography): string {
  if (!t) return '';
  return `font-size: ${t.fontSizePt ?? 12}pt; font-family: ${esc(t.fontFamily)}; font-weight: ${t.fontWeight ?? 700}; color: ${esc(t.textColor)}; text-align: ${esc(t.textAlign)}; line-height: ${t.lineHeight ?? 1.2}; letter-spacing: ${t.letterSpacingPx ?? 0}px;`;
}

/** Shared stylesheet for the compiled single-page sheet. */
const SHEET_CSS = `
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { size: 210mm 297mm; margin: 0; }
    html, body {
      width: 210mm; height: 297mm;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #0f172a;
      background: #ffffff;
      overflow: hidden;
      position: relative;
    }
    .omr-comp { position: absolute; }
    .omr-fiducial {
      position: absolute;
      width: 6mm;
      height: 6mm;
      z-index: 1;
      background: #ffffff;
    }
    .omr-fiducial-square {
      border: 1mm solid #0f172a;
    }
    .omr-fiducial-target {
      border: 0.7mm solid #0f172a;
      border-radius: 50%;
      background: radial-gradient(circle, #0f172a 0 18%, #ffffff 19% 42%, #0f172a 43% 58%, #ffffff 59%);
    }
    .omr-fiducial-aruco {
      border: 0.6mm solid #0f172a;
      background:
        linear-gradient(90deg, #0f172a 25%, transparent 25% 50%, #0f172a 50% 75%, transparent 75%),
        linear-gradient(#0f172a 25%, transparent 25% 50%, #0f172a 50% 75%, transparent 75%);
      background-size: 2mm 2mm;
    }
    .omr-qr-reservation {
      position: absolute;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 1mm;
      padding: 1.5mm;
      border: 0.35mm dashed #64748b;
      border-radius: 1mm;
      background: #ffffff;
      color: #334155;
      text-align: center;
    }
    .omr-qr-placeholder {
      display: grid;
      place-items: center;
      width: 70%;
      aspect-ratio: 1;
      border: 0.5mm solid #0f172a;
      background:
        linear-gradient(90deg, #0f172a 12%, transparent 12% 24%, #0f172a 24% 36%, transparent 36% 48%, #0f172a 48% 60%, transparent 60% 72%, #0f172a 72% 84%, transparent 84%),
        linear-gradient(#0f172a 12%, transparent 12% 24%, #0f172a 24% 36%, transparent 36% 48%, #0f172a 48% 60%, transparent 60% 72%, #0f172a 72% 84%, transparent 84%);
      background-size: 100% 100%;
      color: #ffffff;
      font: 700 5pt/1 monospace;
    }
    .omr-qr-label {
      font: 700 5.5pt/1 monospace;
      text-transform: uppercase;
      letter-spacing: 0.2px;
    }

    .omr-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 6mm;
      border-bottom: 0.6mm solid #0f172a;
      padding-bottom: 1mm;
    }
    .omr-header .omr-title { text-transform: uppercase; line-height: 1.15; }
    .omr-header .omr-subtitle { text-transform: uppercase; letter-spacing: 0.3px; margin-top: 0.5mm; }
    .omr-header .omr-sheet-code {
      font-family: monospace;
      font-weight: 800;
      border: 0.35mm solid #0f172a;
      padding: 0.8mm 1.6mm;
      border-radius: 0.6mm;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .omr-field-box {
      display: flex;
      flex-direction: column;
      gap: 1mm;
      border: 0.4mm solid #334155;
      border-radius: 1mm;
      padding: 1.2mm 2mm;
      background: #f8fafc;
      width: 100%;
      height: 100%;
    }
    .omr-field-label {
      flex: 0 0 auto;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.15;
    }
    .omr-field-input {
      flex: 1 1 auto;
      min-height: 4mm;
      width: 100%;
      background: #ffffff;
    }
    .omr-field-input-dotted { border-bottom: 0.35mm dashed #94a3b8; }
    .omr-field-input-underline { border-bottom: 0.35mm solid #334155; }
    .omr-field-input-box { border: 0.35mm solid #334155; border-radius: 0.7mm; }
    .omr-field-input-multiline { min-height: 9mm; border: 0.35mm solid #334155; border-radius: 0.7mm; }

    .omr-instructions {
      border: 0.35mm solid #cbd5e1;
      border-radius: 1mm;
      padding: 1.5mm 2mm;
      width: 100%;
      height: 100%;
    }

    .omr-id-container {
      border: 0.5mm solid #0f172a;
      border-radius: 1mm;
      padding: 1.5mm 2mm;
      background: #fafafa;
    }
    .omr-id-boxes {
      display: flex;
      justify-content: flex-end;
      gap: 1mm;
      margin-bottom: 1mm;
    }
    .omr-id-box {
      width: 3.6mm;
      height: 4.2mm;
      border: 0.4mm solid #0f172a;
      border-radius: 0.6mm;
      background: #ffffff;
    }
    .omr-id-matrix { display: flex; flex-direction: column; gap: 0.6mm; }
    .omr-id-row { display: flex; align-items: center; justify-content: flex-start; gap: 1.5mm; }
    .omr-id-num {
      font-weight: 800;
      font-family: monospace;
      color: #334155;
      width: 3.5mm;
      flex: 0 0 3.5mm;
      text-align: right;
    }
    .omr-id-bubs { display: flex; gap: 1mm; }
    .omr-id-bub {
      width: ${OMR_BUBBLE_DIAMETER_MM}mm;
      height: ${OMR_BUBBLE_DIAMETER_MM}mm;
      border: 0.35mm solid #1e293b;
      border-radius: 50%;
      background: #ffffff;
    }

    .omr-grid-block { background: #ffffff; }
    .omr-subject-header {
      font-weight: 900;
      background: #0f172a;
      color: #ffffff;
      padding: 0.8mm 1mm;
      border-radius: 0.6mm;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      margin-bottom: 0.8mm;
    }
    .omr-option-header {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 1.5mm;
      font-weight: 900;
      margin-bottom: 0.8mm;
    }
    .omr-q-num-spacer { width: 3.5mm; flex: 0 0 3.5mm; }
    .omr-opt-label { width: ${OMR_BUBBLE_DIAMETER_MM}mm; flex: 0 0 ${OMR_BUBBLE_DIAMETER_MM}mm; text-align: center; font-size: 5.5pt; font-family: monospace; color: #0f172a; }
    .omr-q-row {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 1.5mm;
      margin-bottom: 0.5mm;
    }
    .omr-q-num {
      font-weight: 700;
      font-family: monospace;
      color: #334155;
      width: 3.5mm;
      flex: 0 0 3.5mm;
      text-align: right;
      padding-right: 0.5mm;
      font-size: 6pt;
    }
    .omr-bubs { display: flex; gap: 1.5mm; }
    .omr-bub {
      width: ${OMR_BUBBLE_DIAMETER_MM}mm;
      height: ${OMR_BUBBLE_DIAMETER_MM}mm;
      border: 0.35mm solid #1e293b;
      border-radius: 50%;
      background: #ffffff;
    }
    .omr-signature {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .omr-signature .omr-sign-label { font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; }
    .omr-signature .omr-sign-line { border-bottom: 0.35mm solid #334155; flex: 1; margin: 0.6mm 0; }
    .omr-signature .omr-sign-cert { font-weight: 600; line-height: 1.25; }
`;

/** Render one component's HTML block (absolute positioned inside the sheet body). */
function renderHtmlDecorations(settings: SheetSettings, decorations: SheetHtmlDecorations): string {
  const decorationMarkup: string[] = [];
  const margin = Math.max(0, Number(settings.marginMm) || 0);
  const markerClass = `omr-fiducial omr-fiducial-${settings.markerPattern}`;

  if (decorations.showFiducials) {
    decorationMarkup.push(
      `<div class="${markerClass}" style="top: ${margin}mm; left: ${margin}mm;" aria-label="Top left fiducial marker"></div>`,
      `<div class="${markerClass}" style="top: ${margin}mm; right: ${margin}mm;" aria-label="Top right fiducial marker"></div>`,
      `<div class="${markerClass}" style="bottom: ${margin}mm; left: ${margin}mm;" aria-label="Bottom left fiducial marker"></div>`,
      `<div class="${markerClass}" style="bottom: ${margin}mm; right: ${margin}mm;" aria-label="Bottom right fiducial marker"></div>`,
    );
  }

  if (decorations.qr.visible) {
    const sizeMm = Math.max(20, Math.min(50, Number(decorations.qr.sizeMm) || 26));
    const xMm = Math.max(0, Math.min(210 - sizeMm, Number(decorations.qr.xMm) || 0));
    const yMm = Math.max(0, Math.min(297 - sizeMm, Number(decorations.qr.yMm) || 0));
    decorationMarkup.push(
      `<div class="omr-qr-reservation" data-omr-overlay="${esc(decorations.qr.overlayTag)}" data-omr-qr-size-mm="${sizeMm}" style="left: ${xMm}mm; top: ${yMm}mm; width: ${sizeMm}mm; height: ${sizeMm}mm;">` +
      '<div class="omr-qr-placeholder" aria-hidden="true">QR</div>' +
      '<span class="omr-qr-label">INSTANCE QR</span>' +
      '</div>',
    );
  }

  return decorationMarkup.join('\n  ');
}

function renderFieldHtml(
  label: string,
  typography: OmrTypography,
  inputStyle: InputBoxStyle,
  inputBackgroundColor: string,
  outerStyle: string,
  inputKind: InputValueKind = 'text',
  backgroundColor = 'transparent',
): string {
  const inputClass = inputKind === 'multiline'
    ? 'omr-field-input omr-field-input-multiline'
    : `omr-field-input ${fieldInputStyleClass(inputStyle)}`;
  return `<div class="omr-comp" style="${outerStyle}">
    <div class="omr-field-box" style="background-color: ${safeComponentColor(backgroundColor, 'transparent')};">
      <div class="omr-field-label" style="${typographyToStyleString(typography)}">${esc(label)}</div>
      <div class="${inputClass}" data-input-kind="${esc(inputKind)}" style="background-color: ${safeComponentColor(inputBackgroundColor, '#ffffff')};"></div>
    </div>
  </div>`;
}
function renderComponentHtml(c: OmrComponent): string {
  const style = `left: ${c.posX}mm; top: ${c.posY}mm; width: ${c.w_mm}mm; background-color: ${safeComponentColor(c.backgroundColor)}; z-index: ${Number(c.zIndex) || 1};`;

  if (c.type === 'header') {
    const titleStyle = typographyToStyleString(c.typography);
    return `<div class="omr-comp omr-header" style="${style}">
      <div class="omr-title">
        <div style="${titleStyle}">${esc(c.title)}</div>
        <div class="omr-subtitle" style="font-size: ${Math.max(5, (c.typography?.fontSizePt ?? 13) * 0.55)}pt; color: ${safeComponentColor(c.typography?.textColor, '#475569')}; font-family: ${esc(c.typography?.fontFamily)}; font-weight: 600; text-align: ${esc(c.typography?.textAlign)};">${esc(c.subtitle)}</div>
      </div>
      <div class="omr-sheet-code" style="font-size: 8pt;">${esc(c.formCode)}</div>
    </div>`;
  }

  if (c.type === 'candidate_name' || c.type === 'birthdate' || c.type === 'room_seat') {
    return renderFieldHtml(c.label, c.typography, c.inputStyle || 'box', c.inputBackgroundColor || '#ffffff', style, c.inputKind || 'text', c.backgroundColor);
  }

  if (c.type === 'custom_input') {
    return renderFieldHtml(c.label, c.typography, c.inputStyle || 'box', c.inputBackgroundColor || '#ffffff', style, c.inputKind || 'text', c.backgroundColor);
  }

  if (c.type === 'instructions') {
    return `<div class="omr-comp" style="${style}">
      <div class="omr-instructions" style="border-color: ${safeComponentColor(c.borderColor, '#cbd5e1')}; background: ${safeComponentColor(c.backgroundColor, '#ffffff')}; ${typographyToStyleString(c.typography)}">
        <strong style="text-transform: uppercase;">${esc(c.label || 'Instructions')}:</strong> ${esc(c.content)}
      </div>
    </div>`;
  }

  if (c.type === 'roll') {
    const digits = Number(c.digitsCount) || 7;
    const bubbleDiameter = Number(c.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
    const bubbleStyle = `width: ${bubbleDiameter}mm; height: ${bubbleDiameter}mm;`;
    const boxStyle = `width: ${bubbleDiameter}mm; height: ${(bubbleDiameter * 1.15).toFixed(1)}mm;`;
    const boxes = Array.from({ length: digits }).map(() => `<div class="omr-id-box" style="${boxStyle}"></div>`).join('');
    const rows = Array.from({ length: 10 }).map((_, digit) => `
      <div class="omr-id-row">
        <span class="omr-id-num" style="${typographyToStyleString(c.typography)}">${digit}</span>
        <div class="omr-id-bubs">${Array.from({ length: digits }).map(() => `<div class="omr-id-bub" style="${bubbleStyle}"></div>`).join('')}</div>
      </div>`).join('');
    return `<div class="omr-comp" style="${style}">
      <div class="omr-id-container" style="background-color: ${safeComponentColor(c.backgroundColor, '#fafafa')};">
        <div class="omr-id-title" style="${typographyToStyleString(c.typography)}">${esc(c.title)}</div>
        <div class="omr-id-boxes">${boxes}</div>
        <div class="omr-id-matrix" style="${typographyToStyleString(c.typography)}">${rows}</div>
      </div>
    </div>`;
  }

  if (isQuestionGridComponent(c)) {
    const labels = gridOptionLabels(c);
    const bubbleDiameter = Number(c.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
    const bubbleStyle = `width: ${bubbleDiameter}mm; height: ${bubbleDiameter}mm;`;
    const labelFontSize = Number(c.labelFontSizePt) || 6.5;
    const rows = Array.from({ length: Math.min(Number(c.questionsCount) || 1, 50) }).map((_, r) => {
      const qNum = (Number(c.startQ) || 1) + r;
      return `<div class="omr-q-row"><span class="omr-q-num">${qNum}</span><div class="omr-bubs">${labels.map(() => `<div class="omr-bub" style="${bubbleStyle}"></div>`).join('')}</div></div>`;
    }).join('');
    return `<div class="omr-comp omr-grid-block" style="${style}">
      <div class="omr-subject-header" style="font-size: 6pt;">${esc(c.subjectName || c.name)}</div>
      <div class="omr-option-header" style="font-size: ${labelFontSize}pt;"><span class="omr-q-num-spacer"></span><div class="omr-bubs">${labels.map((l) => `<span class="omr-opt-label" style="font-size: ${labelFontSize}pt; width: ${bubbleDiameter}mm;">${esc(l)}</span>`).join('')}</div></div>
      <div style="font-size: ${labelFontSize}pt; line-height: 1;">${rows}</div>
    </div>`;
  }

  if (c.type === 'custom_text') {
    return `<div class="omr-comp" style="${style}"><div style="${typographyToStyleString(c.typography)}">${esc(c.text)}</div></div>`;
  }

  if (c.type === 'signature') {
    return `<div class="omr-comp" style="${style}">
      <div class="omr-signature">
        <div class="omr-sign-label" style="${typographyToStyleString(c.typography)}">${esc(c.title)}</div>
        <div class="omr-sign-line"></div>
        <div class="omr-sign-cert" style="font-size: 6.5pt; color: #475569;">${esc(c.certificationText)}</div>
      </div>
    </div>`;
  }

  return '';
}

/**
 * Render a single-page A4 HTML sheet from ONLY the components present.
 * Empty component list renders a blank sheet. Each component is absolutely
 * positioned in mm from the sheet edge.
 */
export function renderFullSheetHtml(
  components: OmrComponent[],
  settings: SheetSettings = DEFAULT_A4_SETTINGS,
  decorations: SheetHtmlDecorations = DEFAULT_SHEET_HTML_DECORATIONS,
): string {
  if (!components || components.length === 0) {
    return `<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>${SHEET_CSS}</style></head>
<body></body>
</html>`;
  }

  const decorationMarkup = renderHtmlDecorations(settings, decorations);

  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>${SHEET_CSS}</style>
</head>
<body>
  ${decorationMarkup}
  ${components.map(renderComponentHtml).join('\n  ')}
</body>
</html>`;
}

export interface SectionData {
  id: string;
  name?: string;
  type: 'mcq' | 'tf' | 'likert' | 'custom' | 'roll';
  posX: number;
  posY: number;
  w_mm: number;
  h_mm: number;
  questionsCount: number;
  cols?: number;
  optionsCount?: number;
  startQ?: number;
  customValuesStr?: string;
  bubblePitchMm: number;
  bubbleDiameterMm?: number;
  rowPitchMm: number;
  isLocked: boolean;
}

/**
 * Convert geometric components (roll + question grids) into SectionData objects
 * for OMR geometry/recognition. Text components are HTML-only → null.
 */
export function componentToSectionData(c: OmrComponent): SectionData | null {
  if (c.type === 'roll') {
    return {
      id: c.id,
      name: c.title || 'Examinee Number',
      type: 'roll',
      posX: c.posX,
      posY: c.posY,
      w_mm: rollBlockWidthMm(c),
      h_mm: rollBlockHeightMm(c),
      questionsCount: c.digitsCount,
      cols: c.digitsCount,
      optionsCount: 10,
      startQ: 1,
      customValuesStr: '0, 1, 2, 3, 4, 5, 6, 7, 8, 9',
      bubblePitchMm: c.bubblePitchMm,
      bubbleDiameterMm: c.bubbleDiameterMm,
      rowPitchMm: c.rowPitchMm,
      isLocked: c.isLocked,
    };
  }
  if (isQuestionGridComponent(c)) {
    return {
      id: c.id,
      name: c.name || c.subjectName,
      type: c.type,
      posX: c.posX,
      posY: c.posY,
      w_mm: gridBlockWidthMm(c),
      h_mm: gridBlockHeightMm(c),
      questionsCount: c.questionsCount,
      cols: c.cols || 1,
      optionsCount: c.optionsCount || 4,
      startQ: c.startQ || 1,
      customValuesStr: c.customValuesStr || 'A, B, C, D',
      bubblePitchMm: c.bubblePitchMm,
      rowPitchMm: c.rowPitchMm,
      isLocked: c.isLocked,
    };
  }
  return null;
}

/**
 * 150-Item Examination Answer Sheet Preset — standard A4 layout.
 */
export const PRESET_150Q_COMPONENTS: OmrComponent[] = [
  {
    id: 'Header',
    type: 'header',
    title: 'University General Aptitude Test',
    subtitle: 'Standard 150-Item Examination Answer Sheet • ISO A4',
    formCode: 'FORM 150-A',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 13, fontWeight: 900, textAlign: 'left' },
    posX: 15, posY: 10,
    w_mm: 180, h_mm: 12,
    isLocked: false,
  },
  {
    id: 'Candidate_Name',
    type: 'candidate_name',
    label: 'Candidate Name (Print: Surname, First Name, Middle Initial)',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 800, textColor: '#475569' },
    inputStyle: 'box',
    inputKind: 'text',
    inputBackgroundColor: '#ffffff',
    posX: 15, posY: 24,
    w_mm: 88, h_mm: 14,
    isLocked: false,
  },
  {
    id: 'Birthdate',
    type: 'birthdate',
    label: 'Birthdate (MM / DD / YYYY)',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 800, textColor: '#475569' },
    inputStyle: 'box',
    inputKind: 'date',
    inputBackgroundColor: '#ffffff',
    posX: 15, posY: 36,
    w_mm: 52, h_mm: 14,
    isLocked: false,
  },
  {
    id: 'Room_Seat',
    type: 'room_seat',
    label: 'Room / Seat / Group No.',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 800, textColor: '#475569' },
    inputStyle: 'box',
    inputKind: 'text',
    inputBackgroundColor: '#ffffff',
    posX: 70, posY: 36,
    w_mm: 33, h_mm: 14,
    isLocked: false,
  },
  {
    id: 'Instructions',
    label: 'INSTRUCTIONS',
    type: 'instructions',
    content: 'Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 600, textColor: '#334155', lineHeight: 1.3 },
    borderColor: '#cbd5e1',
    backgroundColor: '#ffffff',
    posX: 15, posY: 48,
    w_mm: 88, h_mm: 28,
    isLocked: false,
  },
  {
    id: 'Examinee_ID',
    type: 'roll',
    title: 'Examinee Number',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 7.5, fontWeight: 700, textColor: '#475569', textAlign: 'center' },
    digitsCount: 7,
    bubbleDiameterMm: 4.5,
    bubblePitchMm: 5.5,
    rowPitchMm: 4.8,
    posX: 110, posY: 24,
    w_mm: 58, h_mm: 52,
    isLocked: false,
  },
  {
    id: 'Part_1',
    name: 'Part 1: English (Q1–25)',
    type: 'mcq',
    subjectName: '1. English',
    questionsCount: 25,
    cols: 1,
    optionsCount: 4,
    startQ: 1,
    customValuesStr: 'A, B, C, D',
    bubblePitchMm: 6.0,
    rowPitchMm: 6.8,
    labelStyle: 'column_header',
    posX: 15, posY: 84,
    w_mm: 28, h_mm: 175,
    isLocked: false,
  },
  {
    id: 'Part_2',
    name: 'Part 2: Math (Q26–50)',
    type: 'mcq',
    subjectName: '2. Math',
    questionsCount: 25,
    cols: 1,
    optionsCount: 4,
    startQ: 26,
    customValuesStr: 'A, B, C, D',
    bubblePitchMm: 6.0,
    rowPitchMm: 6.8,
    labelStyle: 'column_header',
    posX: 45, posY: 84,
    w_mm: 28, h_mm: 175,
    isLocked: false,
  },
  {
    id: 'Part_3',
    name: 'Part 3: Science (Q51–75)',
    type: 'mcq',
    subjectName: '3. Science',
    questionsCount: 25,
    cols: 1,
    optionsCount: 4,
    startQ: 51,
    customValuesStr: 'A, B, C, D',
    bubblePitchMm: 6.0,
    rowPitchMm: 6.8,
    labelStyle: 'column_header',
    posX: 76, posY: 84,
    w_mm: 28, h_mm: 175,
    isLocked: false,
  },
  {
    id: 'Part_4',
    name: 'Part 4: Abstract (Q76–100)',
    type: 'mcq',
    subjectName: '4. Abstract',
    questionsCount: 25,
    cols: 1,
    optionsCount: 4,
    startQ: 76,
    customValuesStr: 'A, B, C, D',
    bubblePitchMm: 6.0,
    rowPitchMm: 6.8,
    labelStyle: 'column_header',
    posX: 106, posY: 84,
    w_mm: 28, h_mm: 175,
    isLocked: false,
  },
  {
    id: 'Part_5',
    name: 'Part 5: Gen Info (Q101–125)',
    type: 'mcq',
    subjectName: '5. Gen Info',
    questionsCount: 25,
    cols: 1,
    optionsCount: 4,
    startQ: 101,
    customValuesStr: 'A, B, C, D',
    bubblePitchMm: 6.0,
    rowPitchMm: 6.8,
    labelStyle: 'column_header',
    posX: 137, posY: 84,
    w_mm: 28, h_mm: 175,
    isLocked: false,
  },
  {
    id: 'Part_6',
    name: 'Part 6: Logic (Q126–150)',
    type: 'mcq',
    subjectName: '6. Logic',
    questionsCount: 25,
    cols: 1,
    optionsCount: 4,
    startQ: 126,
    customValuesStr: 'A, B, C, D',
    bubblePitchMm: 6.0,
    rowPitchMm: 6.8,
    labelStyle: 'column_header',
    posX: 167, posY: 84,
    w_mm: 28, h_mm: 175,
    isLocked: false,
  },
];

export interface ComponentPaletteItem {
  kind: ComponentKind;
  label: string;
  description: string;
  paths: ComponentPath[];
}

export const COMPONENT_PALETTE: ComponentPaletteItem[] = [
  { kind: 'header', label: 'Document Header', description: 'Exam title, subtitle, and form code badge', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'candidate_name', label: 'Candidate Name', description: 'Write-in line for the candidate printed name', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'birthdate', label: 'Birthdate', description: 'Write-in line for birthdate (MM / DD / YYYY)', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'room_seat', label: 'Room / Seat', description: 'Write-in line for room, seat, or group number', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'instructions', label: 'Instructions', description: 'Pencil / shading rules notice box', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'roll', label: 'Examinee ID Matrix', description: 'Write-in boxes + 0–9 vertical bubble columns', paths: ['sheet_designer'] },
  { kind: 'question_grid', label: 'Question Grid', description: 'Numbered question rows with option bubbles', paths: ['sheet_designer'] },
  { kind: 'custom_text', label: 'Custom Text', description: 'Free-positioned text label', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'custom_input', label: 'Custom Input Box', description: 'Labeled response field with configurable input shape', paths: ['sheet_designer'] },
  { kind: 'signature', label: 'Signature Box', description: 'Signature line with certification text', paths: ['sheet_designer', 'upload_document'] },
];

export function paletteForPath(path: ComponentPath): ComponentPaletteItem[] {
  const filter = COMPONENT_PATH_FILTERS[path];
  return COMPONENT_PALETTE.filter((item) => {
    const isQuestionComponent = item.kind === 'roll' || item.kind === 'question_grid';
    return item.paths.includes(path)
      && (filter.includeQuestionComponents || !isQuestionComponent)
      && (filter.includeTextComponents || isQuestionComponent);
  });
}

export type OverlayPaletteKind = 'qr_code' | 'margins' | 'markers';

export interface OverlayPaletteItem {
  kind: OverlayPaletteKind;
  label: string;
  description: string;
  paths: ComponentPath[];
}

export const OVERLAY_PALETTE: OverlayPaletteItem[] = [
  { kind: 'qr_code', label: 'Blank Instance QR', description: 'Reserved QR block for blank-instance tracking', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'margins', label: 'Margin Insets', description: 'Physical quiet zone around the sheet edge', paths: ['sheet_designer', 'upload_document'] },
  { kind: 'markers', label: 'Fiducial Markers', description: 'Corner registration marker pattern', paths: ['sheet_designer', 'upload_document'] },
];

export function overlayPaletteForPath(path: ComponentPath): OverlayPaletteItem[] {
  return OVERLAY_PALETTE.filter((item) => item.paths.includes(path));
}

export const FONT_FAMILY_OPTIONS = [
  { value: 'Inter, sans-serif', label: 'Inter' },
  { value: 'IBM Plex Sans, sans-serif', label: 'IBM Plex Sans' },
  { value: 'Georgia, "Times New Roman", serif', label: 'Georgia / Serif' },
  { value: '"Courier New", Courier, monospace', label: 'Courier / Mono' },
  { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
];

let componentSeq = 0;

/** Reset the component id sequence (used by tests). */
export function resetComponentSeq(): void {
  componentSeq = 0;
}

export interface ComponentPosition {
  posX?: number;
  posY?: number;
}

/**
 * Create a blank component instance of the given kind, optionally at a drop point.
 * Defaults place each kind in a sensible sheet position; repeated adds stagger.
 */
export function createComponent(kind: ComponentKind, position?: ComponentPosition): OmrComponent {
  componentSeq += 1;
  const seq = componentSeq;
  const p = position ?? {};

  switch (kind) {
    case 'header':
      return {
        id: `Header_${seq}`,
        type: 'header',
        title: 'EXAMINATION ANSWER SHEET',
        subtitle: 'Automated OMR Evaluation Form',
        formCode: 'FORM 1-A',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 13, fontWeight: 900, textAlign: 'left' },
        posX: p.posX ?? 15,
        posY: p.posY ?? 10,
        w_mm: 180,
        h_mm: 14,
        isLocked: false,
      };
    case 'candidate_name':
      return {
        id: `CandidateName_${seq}`,
        type: 'candidate_name',
        label: 'Candidate Name (Print: Surname, First Name, Middle Initial)',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        inputStyle: 'box',
        inputKind: 'text',
        inputBackgroundColor: '#ffffff',
        posX: p.posX ?? 15,
        posY: p.posY ?? 28,
        w_mm: 88,
        h_mm: 14,
        isLocked: false,
      };
    case 'birthdate':
      return {
        id: `Birthdate_${seq}`,
        type: 'birthdate',
        label: 'Birthdate (MM / DD / YYYY)',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        inputStyle: 'box',
        inputKind: 'date',
        inputBackgroundColor: '#ffffff',
        posX: p.posX ?? 15,
        posY: p.posY ?? 42,
        w_mm: 52,
        h_mm: 14,
        isLocked: false,
      };
    case 'room_seat':
      return {
        id: `RoomSeat_${seq}`,
        type: 'room_seat',
        label: 'Room / Seat / Group No.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        inputStyle: 'box',
        inputKind: 'text',
        inputBackgroundColor: '#ffffff',
        posX: p.posX ?? 70,
        posY: p.posY ?? 42,
        w_mm: 33,
        h_mm: 14,
        isLocked: false,
      };
    case 'custom_input':
      return {
        id: `CustomInput_${seq}`,
        type: 'custom_input',
        label: 'Custom Response',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 7, fontWeight: 700, textColor: '#475569' },
        inputKind: 'text',
        inputStyle: 'box',
        inputBackgroundColor: '#ffffff',
        posX: p.posX ?? 15,
        posY: p.posY ?? 180,
        w_mm: 80,
        h_mm: 16,
        isLocked: false,
      };
    case 'instructions':
      return {
        id: `Instructions_${seq}`,
        type: 'instructions',
        label: 'INSTRUCTIONS',
        content: 'Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 600, textColor: '#334155', lineHeight: 1.3 },
        borderColor: '#cbd5e1',
        backgroundColor: '#ffffff',
        posX: p.posX ?? 15,
        posY: p.posY ?? 56,
        w_mm: 88,
        h_mm: 24,
        isLocked: false,
      };
    case 'roll':
      return {
        id: `Examinee_ID_${seq}`,
        type: 'roll',
        title: 'Examinee Number',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 7.5, fontWeight: 700, textColor: '#475569', textAlign: 'center' },
        digitsCount: 7,
        bubbleDiameterMm: 4.5,
        bubblePitchMm: 5.5,
        rowPitchMm: 4.8,
        posX: p.posX ?? 110,
        posY: p.posY ?? 28,
        w_mm: 58,
        h_mm: 52,
        isLocked: false,
      };
    case 'question_grid':
      return {
        id: `Grid_${seq}`,
        name: 'Subject Section',
        type: 'mcq',
        subjectName: 'Subject',
        aptitudeAreaCode: undefined,
        questionsCount: 25,
        cols: 1,
        optionsCount: 4,
        startQ: 1,
        customValuesStr: 'A, B, C, D',
        bubbleDiameterMm: 4.5,
        bubblePitchMm: 6.0,
        rowPitchMm: 6.8,
        labelFontSizePt: 6.5,
        labelStyle: 'column_header',
        posX: p.posX ?? 15,
        posY: p.posY ?? 84,
        w_mm: 28,
        h_mm: 175,
        isLocked: false,
      };
    case 'custom_text':
      return {
        id: `Text_${seq}`,
        type: 'custom_text',
        text: 'Custom note / label',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 9, fontWeight: 600 },
        posX: p.posX ?? 15,
        posY: p.posY ?? 265,
        w_mm: 80,
        h_mm: 10,
        isLocked: false,
      };
    case 'signature':
      return {
        id: `Signature_${seq}`,
        type: 'signature',
        title: 'EXAMINEE SIGNATURE',
        certificationText: 'I certify that all answers marked on this sheet are my own.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 7.5, fontWeight: 700, textColor: '#475569' },
        posX: p.posX ?? 115,
        posY: p.posY ?? 265,
        w_mm: 80,
        h_mm: 18,
        isLocked: false,
      };
  }
}


export const COMPONENT_SUITE_CSS = `/* NEXIAM OMR Component Suite — atomic absolute-positioned components (v2) */
* { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: 210mm 297mm; margin: 0; }
body { width: 210mm; height: 297mm; position: relative; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; background: #fff; }
.omr-comp { position: absolute; }
.omr-fiducial { position: absolute; width: 6mm; height: 6mm; z-index: 1; background: #fff; }
.omr-fiducial-square { border: 1mm solid #0f172a; }
.omr-fiducial-target { border: 0.7mm solid #0f172a; border-radius: 50%; background: radial-gradient(circle, #0f172a 0 18%, #fff 19% 42%, #0f172a 43% 58%, #fff 59%); }
.omr-fiducial-aruco { border: 0.6mm solid #0f172a; background: linear-gradient(90deg, #0f172a 25%, transparent 25% 50%, #0f172a 50% 75%, transparent 75%), linear-gradient(#0f172a 25%, transparent 25% 50%, #0f172a 50% 75%, transparent 75%); background-size: 2mm 2mm; }
.omr-qr-reservation { position: absolute; z-index: 2; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1mm; padding: 1.5mm; border: 0.35mm dashed #64748b; border-radius: 1mm; background: #fff; color: #334155; text-align: center; }
.omr-qr-placeholder { display: grid; place-items: center; width: 70%; aspect-ratio: 1; border: 0.5mm solid #0f172a; background: #334155; color: #fff; font: 700 5pt/1 monospace; }
.omr-qr-label { font: 700 5.5pt/1 monospace; text-transform: uppercase; letter-spacing: 0.2px; }
.omr-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 6mm; border-bottom: 0.6mm solid #0f172a; padding-bottom: 1mm; }
.omr-header .omr-title { text-transform: uppercase; line-height: 1.15; }
.omr-header .omr-sheet-code { font-family: monospace; font-weight: 800; border: 0.35mm solid #0f172a; padding: 0.8mm 1.6mm; border-radius: 0.6mm; white-space: nowrap; }
.omr-field-box { display: flex; flex-direction: column; gap: 1mm; border: 0.4mm solid #334155; border-radius: 1mm; padding: 1.2mm 2mm; background: #f8fafc; width: 100%; height: 100%; }
.omr-field-label { flex: 0 0 auto; font-weight: 800; text-transform: uppercase; line-height: 1.15; }
.omr-field-input { flex: 1 1 auto; min-height: 4mm; width: 100%; background: #fff; }
.omr-field-input-dotted { border-bottom: 0.35mm dashed #94a3b8; }
.omr-field-input-underline { border-bottom: 0.35mm solid #334155; }
.omr-field-input-box { border: 0.35mm solid #334155; border-radius: 0.7mm; }
.omr-field-input-multiline { min-height: 9mm; border: 0.35mm solid #334155; border-radius: 0.7mm; }
.omr-instructions { border: 0.35mm solid #cbd5e1; border-radius: 1mm; padding: 1.5mm 2mm; width: 100%; height: 100%; }
.omr-id-container { border: 0.5mm solid #0f172a; border-radius: 1mm; padding: 1.5mm 2mm; background: #fafafa; width: 100%; height: 100%; overflow: hidden; }
.omr-id-title { font-weight: 900; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1mm; }
.omr-id-box { width: 3.6mm; height: 4.2mm; border: 0.4mm solid #0f172a; border-radius: 0.6mm; background: #fff; }
.omr-id-row { display: flex; align-items: center; justify-content: flex-start; gap: 1.5mm; }
.omr-id-num { width: 3.5mm; flex: 0 0 3.5mm; text-align: right; font-family: monospace; }
.omr-id-bubs { display: flex; gap: 1mm; }
.omr-id-bub { width: ${OMR_BUBBLE_DIAMETER_MM}mm; height: ${OMR_BUBBLE_DIAMETER_MM}mm; border: 0.35mm solid #1e293b; border-radius: 50%; background: #fff; }
.omr-grid-block { background: #fff; }
.omr-subject-header { font-weight: 900; background: #0f172a; color: #fff; padding: 0.8mm 1mm; border-radius: 0.6mm; text-align: center; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.8mm; }
.omr-option-header { display: flex; align-items: center; justify-content: flex-start; gap: 1.5mm; font-weight: 900; margin-bottom: 0.8mm; }
.omr-q-num-spacer { width: 3.5mm; flex: 0 0 3.5mm; }
.omr-opt-label { width: ${OMR_BUBBLE_DIAMETER_MM}mm; flex: 0 0 ${OMR_BUBBLE_DIAMETER_MM}mm; text-align: center; font-size: 5.5pt; font-family: monospace; }
.omr-q-row { display: flex; align-items: center; justify-content: flex-start; gap: 1.5mm; margin-bottom: 0.5mm; }
.omr-q-num { font-weight: 700; font-family: monospace; color: #334155; width: 3.5mm; flex: 0 0 3.5mm; text-align: right; padding-right: 0.5mm; }
.omr-bubs { display: flex; gap: 1.5mm; }
.omr-bub { width: ${OMR_BUBBLE_DIAMETER_MM}mm; height: ${OMR_BUBBLE_DIAMETER_MM}mm; border: 0.35mm solid #1e293b; border-radius: 50%; background: #fff; }
.omr-signature { width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
.omr-sign-label { font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; }
.omr-sign-line { border-bottom: 0.35mm solid #334155; flex: 1; margin: 0.6mm 0; }
.omr-sign-cert { font-weight: 600; line-height: 1.25; }`;

export const COMPONENT_SUITE_PROMPT = `You are reproducing a NEXIAM OMR answer sheet using the following atomic component suite contract.
Rules:
1. Every component is an absolutely positioned .omr-comp block with left/top/width in mm from the sheet top-left edge.
2. Render ONLY the components the user asks for — there is no implicit scaffold. Adding a Document Header must not add candidate fields, instructions, or grids.
3. Components are independent: Document Header, Candidate Name, Birthdate, Room/Seat, Instructions, Examinee ID Matrix, Question Grid, Custom Text, Custom Input Box, Signature Box.
4. Question Grids render subject header + option header + numbered rows of bubbles (A–E / T–F / Likert 1–5 / custom values).
5. Fiducials are four absolutely positioned .omr-fiducial elements using the selected marker pattern and margin inset.
6. QR is a reserved data-omr-overlay="blank_instance_qr" block; the signed instance QR is inserted by the print compositor, not fabricated in static HTML.
7. The sheet is a single A4 page: body { width: 210mm; height: 297mm; position: relative; overflow: hidden; }.

CSS contract:
${COMPONENT_SUITE_CSS}`;
