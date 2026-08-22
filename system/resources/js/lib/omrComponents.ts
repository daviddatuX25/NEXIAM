/**
 * Universal OMR Component Suite — atomic sheet components with absolute positioning.
 *
 * Every component owns its own geometry (`posX`/`posY`/`w_mm`/`h_mm` in mm from the
 * sheet edge) and renders independently. `renderFullSheetHtml` emits ONLY the
 * components present — there is no implicit scaffold. Roll + question grids also
 * bridge into OMR section geometry via `componentToSectionData`; text components
 * are HTML-only.
 */

import { contentBlockSizeMm } from './omrStudioGeometry';

export interface OmrTypography {
  fontSizePt: number;
  fontFamily: string;
  fontWeight: 400 | 600 | 700 | 800 | 900;
  textColor: string;
  textAlign: 'left' | 'center' | 'right';
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

/** Geometry shared by every component: mm position/size from the sheet top-left edge. */
export interface ComponentGeometry {
  posX: number;
  posY: number;
  w_mm: number;
  h_mm: number;
  isLocked: boolean;
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
  typography: OmrTypography;
}

export interface InstructionsComponent extends ComponentGeometry {
  id: string;
  type: 'instructions';
  content: string;
  typography: OmrTypography;
  borderColor: string;
  backgroundColor: string;
}

export interface ExamineeIdMatrixComponent extends ComponentGeometry {
  id: string;
  type: 'roll';
  title: string;
  digitsCount: number;
  bubblePitchMm: number;
  rowPitchMm: number;
}

export interface QuestionGridComponent extends ComponentGeometry {
  id: string;
  name: string;
  type: 'mcq' | 'tf' | 'likert' | 'custom';
  subjectName: string;
  questionsCount: number;
  cols: number;
  optionsCount: number;
  startQ: number;
  customValuesStr: string;
  bubblePitchMm: number;
  rowPitchMm: number;
  labelStyle: 'column_header' | 'inline_circle';
}

export interface SignatureBoxComponent extends ComponentGeometry {
  id: string;
  type: 'signature';
  title: string;
  certificationText: string;
  typography: OmrTypography;
}

export interface CustomTextComponent extends ComponentGeometry {
  id: string;
  type: 'custom_text';
  text: string;
  typography: OmrTypography;
}

export type OmrComponent =
  | HeaderComponent
  | TextFieldComponent
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
  | 'signature';

export type TextComponentType = 'header' | 'candidate_name' | 'birthdate' | 'room_seat' | 'instructions' | 'custom_text' | 'signature';
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

export const DEFAULT_A4_SETTINGS: SheetSettings = {
  paperWidthMm: 210,
  paperHeightMm: 297,
  marginMm: 15,
  markerPattern: 'square',
};

export const CANVAS_PX_PER_MM = 2.476; // 520px sheet width ÷ 210mm — canvas scale authority

/** Option labels for a question grid component. */
export function gridOptionLabels(g: QuestionGridComponent): string[] {
  if (g.type === 'tf') return ['T', 'F'];
  if (g.type === 'likert') return ['1', '2', '3', '4', '5'];
  if (g.type === 'custom') return (g.customValuesStr || 'A, B').split(',').map((v) => v.trim()).filter(Boolean);
  const count = Number(g.optionsCount) || 4;
  return ['A', 'B', 'C', 'D', 'E'].slice(0, Math.max(2, Math.min(5, count)));
}

/** Content-derived block width in mm for a question grid (matches StudioCanvas section math). */
export function gridBlockWidthMm(g: QuestionGridComponent): number {
  const opts = gridOptionLabels(g).length;
  const rows = Number(g.questionsCount) || 1;
  return contentBlockSizeMm(opts, rows, Number(g.bubblePitchMm) || 6.0, Number(g.rowPitchMm) || 6.8).w_mm;
}

/** Content-derived block height in mm for a question grid. */
export function gridBlockHeightMm(g: QuestionGridComponent): number {
  const opts = gridOptionLabels(g).length;
  const rows = Number(g.questionsCount) || 1;
  return contentBlockSizeMm(opts, rows, Number(g.bubblePitchMm) || 6.0, Number(g.rowPitchMm) || 6.8).h_mm;
}

/** Content-derived block width in mm for an examinee ID matrix. */
export function rollBlockWidthMm(r: ExamineeIdMatrixComponent): number {
  const digits = Number(r.digitsCount) || 7;
  return Number((4.5 + (digits - 1) * (Number(r.bubblePitchMm) || 5.5) + 1.5).toFixed(1));
}

/** Content-derived block height in mm for an examinee ID matrix (10 digit rows). */
export function rollBlockHeightMm(r: ExamineeIdMatrixComponent): number {
  return Number((4.5 + 9 * (Number(r.rowPitchMm) || 4.8) + 1.5).toFixed(1));
}

/** Canvas block size for any component: stored size for text, content-derived for roll/grids. */
export function componentBlockSizeMm(c: OmrComponent): { w_mm: number; h_mm: number } {
  if (c.type === 'roll') return { w_mm: rollBlockWidthMm(c), h_mm: rollBlockHeightMm(c) };
  if (isQuestionGridComponent(c)) return { w_mm: gridBlockWidthMm(c), h_mm: gridBlockHeightMm(c) };
  return { w_mm: Number(c.w_mm) || 40, h_mm: Number(c.h_mm) || 12 };
}

/** Escape text for HTML attribute/innerText interpolation. */
function esc(value: string | number | undefined | null): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
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
      border: 0.4mm solid #334155;
      border-radius: 1mm;
      padding: 1.2mm 2mm;
      background: #f8fafc;
      width: 100%;
      height: 100%;
    }
    .omr-field-label {
      font-weight: 800;
      text-transform: uppercase;
      margin-bottom: 0.5mm;
    }
    .omr-field-line {
      border-bottom: 0.35mm dashed #94a3b8;
      height: 4mm;
    }

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
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    .omr-id-title {
      font-weight: 900;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 1mm;
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
    .omr-id-row { display: flex; align-items: center; justify-content: space-between; }
    .omr-id-num {
      font-weight: 800;
      font-family: monospace;
      color: #334155;
      width: 3mm;
      text-align: center;
    }
    .omr-id-bubs { display: flex; gap: 1mm; }
    .omr-id-bub {
      width: 3.6mm;
      height: 2.8mm;
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
      justify-content: flex-end;
      gap: 1.2mm;
      font-weight: 900;
      margin-bottom: 0.8mm;
      padding-right: 0.4mm;
    }
    .omr-opt-label { width: 3mm; text-align: center; }
    .omr-q-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .omr-q-num {
      font-weight: 700;
      font-family: monospace;
      color: #334155;
      width: 4.2mm;
      text-align: right;
      padding-right: 0.8mm;
    }
    .omr-bubs { display: flex; gap: 1.2mm; }
    .omr-bub {
      width: 3mm;
      height: 2.8mm;
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
function renderComponentHtml(c: OmrComponent): string {
  const style = `left: ${c.posX}mm; top: ${c.posY}mm; width: ${c.w_mm}mm;`;

  if (c.type === 'header') {
    const titleStyle = typographyToStyleString(c.typography);
    return `<div class="omr-comp omr-header" style="${style}">
      <div class="omr-title">
        <div style="${titleStyle}">${esc(c.title)}</div>
        <div class="omr-subtitle" style="font-size: 7pt; color: #475569; font-weight: 600;">${esc(c.subtitle)}</div>
      </div>
      <div class="omr-sheet-code" style="font-size: 8pt;">${esc(c.formCode)}</div>
    </div>`;
  }

  if (c.type === 'candidate_name' || c.type === 'birthdate' || c.type === 'room_seat') {
    return `<div class="omr-comp" style="${style}">
      <div class="omr-field-box">
        <div class="omr-field-label" style="${typographyToStyleString(c.typography)}">${esc(c.label)}</div>
        <div class="omr-field-line"></div>
      </div>
    </div>`;
  }

  if (c.type === 'instructions') {
    return `<div class="omr-comp" style="${style}">
      <div class="omr-instructions" style="border-color: ${esc(c.borderColor)}; background: ${esc(c.backgroundColor)}; ${typographyToStyleString(c.typography)}">
        <strong style="text-transform: uppercase;">Instructions:</strong> ${esc(c.content)}
      </div>
    </div>`;
  }

  if (c.type === 'roll') {
    const digits = Number(c.digitsCount) || 7;
    const boxes = Array.from({ length: digits }).map(() => '<div class="omr-id-box"></div>').join('');
    const rows = Array.from({ length: 10 }).map((_, digit) => `
      <div class="omr-id-row">
        <span class="omr-id-num">${digit}</span>
        <div class="omr-id-bubs">${Array.from({ length: digits }).map(() => '<div class="omr-id-bub"></div>').join('')}</div>
      </div>`).join('');
    return `<div class="omr-comp" style="${style}">
      <div class="omr-id-container">
        <div class="omr-id-title" style="font-size: 6.5pt;">${esc(c.title)}</div>
        <div class="omr-id-boxes">${boxes}</div>
        <div class="omr-id-matrix" style="font-size: 6pt;">${rows}</div>
      </div>
    </div>`;
  }

  if (isQuestionGridComponent(c)) {
    const labels = gridOptionLabels(c);
    const rows = Array.from({ length: Math.min(Number(c.questionsCount) || 1, 60) }).map((_, r) => {
      const qNum = (Number(c.startQ) || 1) + r;
      return `<div class="omr-q-row"><span class="omr-q-num">${qNum}</span><div class="omr-bubs">${labels.map(() => '<div class="omr-bub"></div>').join('')}</div></div>`;
    }).join('');
    return `<div class="omr-comp omr-grid-block" style="${style}">
      <div class="omr-subject-header" style="font-size: 6pt;">${esc(c.subjectName || c.name)}</div>
      <div class="omr-option-header" style="font-size: 5.5pt;">${labels.map((l) => `<span class="omr-opt-label">${esc(l)}</span>`).join('')}</div>
      <div style="font-size: 6pt; line-height: 1;">${rows}</div>
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
export function renderFullSheetHtml(components: OmrComponent[], settings: SheetSettings = DEFAULT_A4_SETTINGS): string {
  if (!components || components.length === 0) {
    return `<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>${SHEET_CSS}</style></head>
<body></body>
</html>`;
  }

  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>${SHEET_CSS}</style>
</head>
<body>
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
    posX: 15, posY: 24,
    w_mm: 88, h_mm: 10,
    isLocked: false,
  },
  {
    id: 'Birthdate',
    type: 'birthdate',
    label: 'Birthdate (MM / DD / YYYY)',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 800, textColor: '#475569' },
    posX: 15, posY: 36,
    w_mm: 52, h_mm: 10,
    isLocked: false,
  },
  {
    id: 'Room_Seat',
    type: 'room_seat',
    label: 'Room / Seat / Group No.',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 800, textColor: '#475569' },
    posX: 70, posY: 36,
    w_mm: 33, h_mm: 10,
    isLocked: false,
  },
  {
    id: 'Instructions',
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
    digitsCount: 7,
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

export const COMPONENT_PALETTE: Array<{ kind: ComponentKind; label: string; description: string }> = [
  { kind: 'header', label: 'Document Header', description: 'Exam title, subtitle, and form code badge' },
  { kind: 'candidate_name', label: 'Candidate Name', description: 'Write-in line for the candidate printed name' },
  { kind: 'birthdate', label: 'Birthdate', description: 'Write-in line for birthdate (MM / DD / YYYY)' },
  { kind: 'room_seat', label: 'Room / Seat', description: 'Write-in line for room, seat, or group number' },
  { kind: 'instructions', label: 'Instructions', description: 'Pencil / shading rules notice box' },
  { kind: 'roll', label: 'Examinee ID Matrix', description: 'Write-in boxes + 0–9 vertical bubble columns' },
  { kind: 'question_grid', label: 'Question Grid', description: 'Numbered question rows with option bubbles' },
  { kind: 'custom_text', label: 'Custom Text', description: 'Free-positioned text label' },
  { kind: 'signature', label: 'Signature Box', description: 'Signature line with certification text' },
];

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
  const posX = p.posX ?? 10;
  const posY = p.posY ?? 10;

  switch (kind) {
    case 'header':
      return {
        id: `Header_${seq}`,
        type: 'header',
        title: 'EXAMINATION ANSWER SHEET',
        subtitle: 'Automated OMR Evaluation Form',
        formCode: 'FORM 1-A',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 13, fontWeight: 900, textAlign: 'left' },
        posX, posY,
        w_mm: 160, h_mm: 18,
        isLocked: false,
      };
    case 'candidate_name':
      return {
        id: `CandidateName_${seq}`,
        type: 'candidate_name',
        label: 'Candidate Name (Print: Surname, First Name, Middle Initial)',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        posX, posY,
        w_mm: 90, h_mm: 12,
        isLocked: false,
      };
    case 'birthdate':
      return {
        id: `Birthdate_${seq}`,
        type: 'birthdate',
        label: 'Birthdate (MM / DD / YYYY)',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        posX, posY,
        w_mm: 55, h_mm: 12,
        isLocked: false,
      };
    case 'room_seat':
      return {
        id: `RoomSeat_${seq}`,
        type: 'room_seat',
        label: 'Room / Seat / Group No.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        posX, posY,
        w_mm: 40, h_mm: 12,
        isLocked: false,
      };
    case 'instructions':
      return {
        id: `Instructions_${seq}`,
        type: 'instructions',
        content: 'Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 600, textColor: '#334155', lineHeight: 1.3 },
        borderColor: '#cbd5e1',
        backgroundColor: '#ffffff',
        posX, posY,
        w_mm: 170, h_mm: 16,
        isLocked: false,
      };
    case 'roll':
      return {
        id: `Examinee_ID_${seq}`,
        type: 'roll',
        title: 'Examinee Number',
        digitsCount: 7,
        bubblePitchMm: 5.5,
        rowPitchMm: 4.8,
        posX, posY,
        w_mm: 58, h_mm: 54,
        isLocked: false,
      };
    case 'question_grid':
      return {
        id: `Grid_${seq}`,
        name: 'Subject Section',
        type: 'mcq',
        subjectName: 'Subject',
        questionsCount: 25,
        cols: 1,
        optionsCount: 4,
        startQ: 1,
        customValuesStr: 'A, B, C, D',
        bubblePitchMm: 6.0,
        rowPitchMm: 6.8,
        labelStyle: 'column_header',
        posX, posY,
        w_mm: 28, h_mm: 175,
        isLocked: false,
      };
    case 'custom_text':
      return {
        id: `Text_${seq}`,
        type: 'custom_text',
        text: 'Custom note / label',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 9, fontWeight: 600 },
        posX, posY,
        w_mm: 80, h_mm: 10,
        isLocked: false,
      };
    case 'signature':
      return {
        id: `Signature_${seq}`,
        type: 'signature',
        title: 'EXAMINEE SIGNATURE',
        certificationText: 'I certify that all answers marked on this sheet are my own.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 8.5, fontWeight: 700, textColor: '#475569' },
        posX, posY,
        w_mm: 80, h_mm: 18,
        isLocked: false,
      };
  }
}


export const COMPONENT_SUITE_CSS = `/* NEXIAM OMR Component Suite — atomic absolute-positioned components (v2) */
* { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: 210mm 297mm; margin: 0; }
body { width: 210mm; height: 297mm; position: relative; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; background: #fff; }
.omr-comp { position: absolute; }
.omr-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 6mm; border-bottom: 0.6mm solid #0f172a; padding-bottom: 1mm; }
.omr-header .omr-title { text-transform: uppercase; line-height: 1.15; }
.omr-header .omr-sheet-code { font-family: monospace; font-weight: 800; border: 0.35mm solid #0f172a; padding: 0.8mm 1.6mm; border-radius: 0.6mm; white-space: nowrap; }
.omr-field-box { border: 0.4mm solid #334155; border-radius: 1mm; padding: 1.2mm 2mm; background: #f8fafc; width: 100%; height: 100%; }
.omr-field-label { font-weight: 800; text-transform: uppercase; margin-bottom: 0.5mm; }
.omr-field-line { border-bottom: 0.35mm dashed #94a3b8; height: 4mm; }
.omr-instructions { border: 0.35mm solid #cbd5e1; border-radius: 1mm; padding: 1.5mm 2mm; width: 100%; height: 100%; }
.omr-id-container { border: 0.5mm solid #0f172a; border-radius: 1mm; padding: 1.5mm 2mm; background: #fafafa; width: 100%; height: 100%; overflow: hidden; }
.omr-id-title { font-weight: 900; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1mm; }
.omr-id-box { width: 3.6mm; height: 4.2mm; border: 0.4mm solid #0f172a; border-radius: 0.6mm; background: #fff; }
.omr-id-bub { width: 3.6mm; height: 2.8mm; border: 0.35mm solid #1e293b; border-radius: 50%; background: #fff; }
.omr-grid-block { background: #fff; }
.omr-subject-header { font-weight: 900; background: #0f172a; color: #fff; padding: 0.8mm 1mm; border-radius: 0.6mm; text-align: center; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.8mm; }
.omr-option-header { display: flex; justify-content: flex-end; gap: 1.2mm; font-weight: 900; margin-bottom: 0.8mm; padding-right: 0.4mm; }
.omr-q-row { display: flex; align-items: center; justify-content: space-between; }
.omr-q-num { font-weight: 700; font-family: monospace; color: #334155; width: 4.2mm; text-align: right; padding-right: 0.8mm; }
.omr-bubs { display: flex; gap: 1.2mm; }
.omr-bub { width: 3mm; height: 2.8mm; border: 0.35mm solid #1e293b; border-radius: 50%; background: #fff; }
.omr-signature { width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
.omr-sign-label { font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; }
.omr-sign-line { border-bottom: 0.35mm solid #334155; flex: 1; margin: 0.6mm 0; }
.omr-sign-cert { font-weight: 600; line-height: 1.25; }`;

export const COMPONENT_SUITE_PROMPT = `You are reproducing a NEXIAM OMR answer sheet using the following atomic component suite contract.
Rules:
1. Every component is an absolutely positioned .omr-comp block with left/top/width in mm from the sheet top-left edge.
2. Render ONLY the components the user asks for — there is no implicit scaffold. Adding a Document Header must not add candidate fields, instructions, or grids.
3. Components are independent: Document Header (title/subtitle/form code), Candidate Name, Birthdate, Room/Seat, Instructions, Examinee ID Matrix, Question Grid, Custom Text, Signature Box.
4. Question Grids render subject header + option header + numbered rows of bubbles (A–E / T–F / Likert 1–5 / custom values).
5. The sheet is a single A4 page: body { width: 210mm; height: 297mm; position: relative; overflow: hidden; }.

CSS contract:
${COMPONENT_SUITE_CSS}`;
