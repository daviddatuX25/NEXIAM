/**
 * Universal OMR Component Suite
 *
 * Defines the shared component data schemas, typography resolver, HTML/CSS printer engine,
 * and OMRChecker fieldBlocks mapping compiler for both Path A (Native Builder) and Path B (Substrate Ingest).
 */

import type { StudioBlock } from './omrStudioGeometry';

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

export interface HeaderComponent {
  id: string;
  type: 'header';
  title: string;
  subtitle: string;
  formCode: string;
  typography: OmrTypography;
  showBorderBottom: boolean;
}

export interface CandidateInfoComponent {
  id: string;
  type: 'candidate_info';
  candidateNameLabel: string;
  birthdateLabel: string;
  roomSeatLabel: string;
  typography: OmrTypography;
  heightMm: number;
}

export interface InstructionsComponent {
  id: string;
  type: 'instructions';
  content: string;
  showMarkExamples: boolean;
  typography: OmrTypography;
  borderColor: string;
  backgroundColor: string;
}

export interface ExamineeIdMatrixComponent {
  id: string;
  type: 'roll';
  title: string;
  digitsCount: number;
  bubblePitchMm: number;
  rowPitchMm: number;
  posX: number;
  posY: number;
  w_mm: number;
  h_mm: number;
  isLocked: boolean;
}

export interface QuestionGridComponent {
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
  posX: number;
  posY: number;
  w_mm: number;
  h_mm: number;
  isLocked: boolean;
}

export interface SingleQuestionComponent {
  id: string;
  type: 'single_q';
  questionNumber: number;
  optionFormat: 'mcq' | 'tf' | 'likert' | 'custom';
  optionsCount: number;
  customValuesStr: string;
  bubblePitchMm: number;
  posX: number;
  posY: number;
  isLocked: boolean;
}

export interface SignatureBoxComponent {
  id: string;
  type: 'signature';
  title: string;
  certificationText: string;
  heightMm: number;
  typography: OmrTypography;
}

export interface CustomTextComponent {
  id: string;
  type: 'custom_text';
  text: string;
  posX: number;
  posY: number;
  typography: OmrTypography;
}

export type OmrComponent =
  | HeaderComponent
  | CandidateInfoComponent
  | InstructionsComponent
  | ExamineeIdMatrixComponent
  | QuestionGridComponent
  | SingleQuestionComponent
  | SignatureBoxComponent
  | CustomTextComponent;

export interface SheetSettings {
  paperWidthMm: number;
  paperHeightMm: number;
  paddingTopMm: number;
  paddingBottomMm: number;
  paddingLeftMm: number;
  paddingRightMm: number;
  marginMm: number;
  markerPattern: 'square' | 'target' | 'aruco';
}

export const DEFAULT_A4_SETTINGS: SheetSettings = {
  paperWidthMm: 210,
  paperHeightMm: 297,
  paddingTopMm: 8,
  paddingBottomMm: 8,
  paddingLeftMm: 10,
  paddingRightMm: 10,
  marginMm: 15,
  markerPattern: 'square',
};

/**
 * 150-Item Examination Answer Sheet Preset
 */
export const PRESET_150Q_COMPONENTS: OmrComponent[] = [
  {
    id: 'Header',
    type: 'header',
    title: 'University General Aptitude Test',
    subtitle: 'Standard 150-Item Examination Answer Sheet • ISO A4',
    formCode: 'FORM 150-A',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 13, fontWeight: 900, textAlign: 'left' },
    showBorderBottom: true,
  },
  {
    id: 'Candidate_Info',
    type: 'candidate_info',
    candidateNameLabel: 'Candidate Name (Print: Surname, First Name, Middle Initial)',
    birthdateLabel: 'Birthdate (MM / DD / YYYY)',
    roomSeatLabel: 'Room / Seat / Group No.',
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 800, textColor: '#475569' },
    heightMm: 24,
  },
  {
    id: 'Instructions',
    type: 'instructions',
    content: 'Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.',
    showMarkExamples: false,
    typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6, fontWeight: 600, textColor: '#334155', lineHeight: 1.3 },
    borderColor: '#cbd5e1',
    backgroundColor: '#ffffff',
  },
  {
    id: 'Examinee_ID',
    type: 'roll',
    title: 'Examinee Number',
    digitsCount: 7,
    bubblePitchMm: 5.5,
    rowPitchMm: 4.8,
    posX: 125,
    posY: 22,
    w_mm: 58,
    h_mm: 54,
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
    posX: 10,
    posY: 88,
    w_mm: 28,
    h_mm: 175,
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
    posX: 42,
    posY: 88,
    w_mm: 28,
    h_mm: 175,
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
    posX: 74,
    posY: 88,
    w_mm: 28,
    h_mm: 175,
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
    posX: 106,
    posY: 88,
    w_mm: 28,
    h_mm: 175,
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
    posX: 138,
    posY: 88,
    w_mm: 28,
    h_mm: 175,
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
    posX: 170,
    posY: 88,
    w_mm: 28,
    h_mm: 175,
    isLocked: false,
  },
];

/**
 * Render complete single-page A4 HTML from component tree
 */
export function renderFullSheetHtml(components: OmrComponent[], settings: SheetSettings = DEFAULT_A4_SETTINGS): string {
  const header = components.find((c): c is HeaderComponent => c.type === 'header');
  const candidateInfo = components.find((c): c is CandidateInfoComponent => c.type === 'candidate_info');
  const instructions = components.find((c): c is InstructionsComponent => c.type === 'instructions');
  const rollId = components.find((c): c is ExamineeIdMatrixComponent => c.type === 'roll');
  const questionGrids = components.filter((c): c is QuestionGridComponent => c.type === 'mcq' || c.type === 'tf' || c.type === 'likert' || c.type === 'custom');

  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      width: 100%; height: 100%;
      padding: ${settings.paddingTopMm}mm ${settings.paddingRightMm}mm ${settings.paddingBottomMm}mm ${settings.paddingLeftMm}mm;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #0f172a;
      background: #ffffff;
      overflow: hidden;
    }

    .omr-header {
      border-bottom: 2px solid #0f172a;
      padding-bottom: 3px;
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }
    .omr-title h1 {
      font-size: ${header?.typography.fontSizePt ?? 13}pt;
      font-weight: ${header?.typography.fontWeight ?? 900};
      color: ${header?.typography.textColor ?? '#0f172a'};
      text-align: ${header?.typography.textAlign ?? 'left'};
      text-transform: uppercase;
      line-height: 1.1;
    }
    .omr-title p {
      font-size: 7pt;
      color: #475569;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      margin-top: 1px;
    }
    .omr-sheet-code {
      font-family: monospace;
      font-size: 8pt;
      font-weight: 800;
      color: #0f172a;
      border: 1px solid #0f172a;
      padding: 2px 5px;
      border-radius: 2px;
    }

    .omr-top-section {
      display: grid;
      grid-template-columns: 1fr 150px;
      gap: 10px;
      margin-bottom: 8px;
      align-items: start;
    }
    .omr-meta-block {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .omr-field-box {
      border: 1.2px solid #334155;
      border-radius: 3px;
      padding: 3px 6px;
      background: #f8fafc;
    }
    .omr-field-label {
      font-size: ${candidateInfo?.typography.fontSizePt ?? 6}pt;
      font-weight: ${candidateInfo?.typography.fontWeight ?? 800};
      text-transform: uppercase;
      color: ${candidateInfo?.typography.textColor ?? '#475569'};
      margin-bottom: 1px;
    }
    .omr-field-line {
      border-bottom: 1px dashed #94a3b8;
      height: 11px;
    }
    .omr-meta-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
    }
    .omr-instructions {
      border: 1px solid ${instructions?.borderColor ?? '#cbd5e1'};
      border-radius: 3px;
      padding: 3px 6px;
      background: ${instructions?.backgroundColor ?? '#ffffff'};
      font-size: ${instructions?.typography.fontSizePt ?? 6}pt;
      color: ${instructions?.typography.textColor ?? '#334155'};
      line-height: ${instructions?.typography.lineHeight ?? 1.3};
    }

    .omr-id-container {
      border: 1.5px solid #0f172a;
      border-radius: 3px;
      padding: 4px 5px;
      background: #fafafa;
    }
    .omr-id-title {
      font-size: 6.5pt;
      font-weight: 900;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #0f172a;
      margin-bottom: 3px;
    }
    .omr-id-boxes {
      display: flex;
      justify-content: flex-end;
      gap: 3px;
      margin-bottom: 3px;
    }
    .omr-id-box {
      width: 11px;
      height: 13px;
      border: 1.2px solid #0f172a;
      border-radius: 2px;
      background: #ffffff;
    }
    .omr-id-matrix {
      display: flex;
      flex-direction: column;
      gap: 1.8px;
    }
    .omr-id-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .omr-id-num {
      font-size: 6pt;
      font-weight: 800;
      font-family: monospace;
      color: #334155;
      width: 8px;
      text-align: center;
    }
    .omr-id-bubs {
      display: flex;
      gap: 3px;
    }
    .omr-id-bub {
      width: 11px;
      height: 8.5px;
      border: 1.1px solid #1e293b;
      border-radius: 50%;
      background: #ffffff;
    }

    .omr-exam-grid {
      display: grid;
      grid-template-columns: repeat(${Math.max(1, questionGrids.length)}, 1fr);
      gap: 5px;
      border-top: 1.5px solid #0f172a;
      padding-top: 5px;
    }
    .omr-col {
      background: #ffffff;
    }
    .omr-subject-header {
      font-size: 6pt;
      font-weight: 900;
      background: #0f172a;
      color: #ffffff;
      padding: 2px 3px;
      border-radius: 2px;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      margin-bottom: 2px;
    }
    .omr-option-header {
      display: flex;
      justify-content: flex-end;
      gap: 3.5px;
      font-size: 5.5pt;
      font-weight: 900;
      color: #0f172a;
      margin-bottom: 2px;
      padding-right: 1px;
    }
    .omr-opt-label {
      width: 9px;
      text-align: center;
    }

    .omr-q-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.2px;
    }
    .omr-q-num {
      font-size: 6pt;
      font-weight: 700;
      font-family: monospace;
      color: #334155;
      width: 11px;
      text-align: right;
      padding-right: 2px;
    }
    .omr-bubs {
      display: flex;
      gap: 3.5px;
    }
    .omr-bub {
      width: 9px;
      height: 8.5px;
      border: 1.1px solid #1e293b;
      border-radius: 50%;
      background: #ffffff;
    }
  </style>
</head>
<body>
  <div class="omr-header">
    <div class="omr-title">
      <h1>${header?.title || 'University General Aptitude Test'}</h1>
      <p>${header?.subtitle || 'Standard 150-Item Examination Answer Sheet • ISO A4'}</p>
    </div>
    <div class="omr-sheet-code">${header?.formCode || 'FORM 150-A'}</div>
  </div>

  <div class="omr-top-section">
    <div class="omr-meta-block">
      <div class="omr-field-box">
        <div class="omr-field-label">${candidateInfo?.candidateNameLabel || 'Candidate Name (Print: Surname, First Name, Middle Initial)'}</div>
        <div class="omr-field-line"></div>
      </div>
      <div class="omr-meta-row">
        <div class="omr-field-box">
          <div class="omr-field-label">${candidateInfo?.birthdateLabel || 'Birthdate (MM / DD / YYYY)'}</div>
          <div class="omr-field-line"></div>
        </div>
        <div class="omr-field-box">
          <div class="omr-field-label">${candidateInfo?.roomSeatLabel || 'Room / Seat / Group No.'}</div>
          <div class="omr-field-line"></div>
        </div>
      </div>
      <div class="omr-instructions">
        <strong>INSTRUCTIONS:</strong> ${instructions?.content || 'Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.'}
      </div>
    </div>

    <div class="omr-id-container">
      <div class="omr-id-title">${rollId?.title || 'Examinee Number'}</div>
      <div class="omr-id-boxes">
        ${Array.from({ length: rollId?.digitsCount ?? 7 }).map(() => '<div class="omr-id-box"></div>').join('')}
      </div>
      <div class="omr-id-matrix">
        ${Array.from({ length: 10 }).map((_, digit) => `
          <div class="omr-id-row">
            <span class="omr-id-num">${digit}</span>
            <div class="omr-id-bubs">
              ${Array.from({ length: rollId?.digitsCount ?? 7 }).map(() => '<div class="omr-id-bub"></div>').join('')}
            </div>
          </div>
        `).join('')}
      </div>
    </div>
  </div>

  <div class="omr-exam-grid">
    ${questionGrids.map((g) => {
      const opts = g.optionsCount || 4;
      const optLabels = g.type === 'tf' ? ['T', 'F'] : g.type === 'likert' ? ['1', '2', '3', '4', '5'] : ['A', 'B', 'C', 'D', 'E'].slice(0, opts);
      const rows = Array.from({ length: g.questionsCount }).map((_, r) => {
        const qNum = g.startQ + r;
        return `<div class="omr-q-row"><span class="omr-q-num">${qNum}</span><div class="omr-bubs">${optLabels.map(() => '<div class="omr-bub"></div>').join('')}</div></div>`;
      }).join('');

      return `<div class="omr-col">
        <div class="omr-subject-header">${g.subjectName || g.name}</div>
        <div class="omr-option-header">${optLabels.map(l => `<span class="omr-opt-label">${l}</span>`).join('')}</div>
        ${rows}
      </div>`;
    }).join('')}
  </div>
</body>
</html>`;
}

/**
 * Convert an OmrTypography object into inline CSS style declaration
 */
export function typographyToStyleString(t?: OmrTypography): string {
  if (!t) return '';
  const styles = [
    `font-size: ${t.fontSizePt}pt;`,
    `font-family: ${t.fontFamily};`,
    `font-weight: ${t.fontWeight};`,
    `color: ${t.textColor};`,
    `text-align: ${t.textAlign};`,
  ];
  if (t.lineHeight) styles.push(`line-height: ${t.lineHeight};`);
  if (t.letterSpacingPx) styles.push(`letter-spacing: ${t.letterSpacingPx}px;`);
  return styles.join(' ');
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
 * Convert universal components into SectionData objects for OMR Studio State
 */
export function componentToSectionData(c: OmrComponent): SectionData | null {
  if (c.type === 'roll') {
    return {
      id: c.id,
      name: c.title || 'Examinee Number',
      type: 'roll',
      posX: c.posX,
      posY: c.posY,
      w_mm: c.w_mm,
      h_mm: c.h_mm,
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
  if (c.type === 'mcq' || c.type === 'tf' || c.type === 'likert' || c.type === 'custom') {
    return {
      id: c.id,
      name: c.name || c.subjectName,
      type: c.type,
      posX: c.posX,
      posY: c.posY,
      w_mm: c.w_mm,
      h_mm: c.h_mm,
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

export type ComponentKind = 'header' | 'candidate_info' | 'instructions' | 'roll' | 'question_grid' | 'custom_text' | 'signature';

export const COMPONENT_PALETTE: Array<{ kind: ComponentKind; label: string; description: string }> = [
  { kind: 'header', label: 'Document Header', description: 'Exam title, subtitle, and form code badge' },
  { kind: 'candidate_info', label: 'Candidate Info', description: 'Name line, birthdate, and room/seat write-in boxes' },
  { kind: 'instructions', label: 'Instructions', description: 'Pencil / shading rules notice box' },
  { kind: 'roll', label: 'Examinee ID Matrix', description: 'Write-in boxes + 0–9 vertical bubble columns' },
  { kind: 'question_grid', label: 'Question Grid', description: 'Numbered question rows with A–D option bubbles' },
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

/**
 * Create a blank component instance of the given kind.
 */
export function createComponent(kind: ComponentKind): OmrComponent {
  componentSeq += 1;
  const seq = componentSeq;
  switch (kind) {
    case 'header':
      return {
        id: `Header_${seq}`,
        type: 'header',
        title: 'EXAMINATION ANSWER SHEET',
        subtitle: 'Automated OMR Evaluation Form',
        formCode: 'FORM 1-A',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 13, fontWeight: 900, textAlign: 'left' },
        showBorderBottom: true,
      };
    case 'candidate_info':
      return {
        id: `Candidate_${seq}`,
        type: 'candidate_info',
        candidateNameLabel: 'Candidate Name (Print: Surname, First Name, Middle Initial)',
        birthdateLabel: 'Birthdate (MM / DD / YYYY)',
        roomSeatLabel: 'Room / Seat / Group No.',
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 800, textColor: '#475569' },
        heightMm: 24,
      };
    case 'instructions':
      return {
        id: `Instructions_${seq}`,
        type: 'instructions',
        content: 'Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.',
        showMarkExamples: true,
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 6.5, fontWeight: 600, textColor: '#334155', lineHeight: 1.3 },
        borderColor: '#cbd5e1',
        backgroundColor: '#ffffff',
      };
    case 'roll':
      return {
        id: `Examinee_ID_${seq}`,
        type: 'roll',
        title: 'Examinee Number',
        digitsCount: 7,
        bubblePitchMm: 5.5,
        rowPitchMm: 4.8,
        posX: 125,
        posY: 22,
        w_mm: 58,
        h_mm: 54,
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
        posX: 10,
        posY: 88,
        w_mm: 28,
        h_mm: 175,
        isLocked: false,
      };
    case 'custom_text':
      return {
        id: `Text_${seq}`,
        type: 'custom_text',
        text: 'Custom note / label',
        posX: 25,
        posY: 250,
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 9, fontWeight: 600 },
      };
    case 'signature':
      return {
        id: `Signature_${seq}`,
        type: 'signature',
        title: 'EXAMINEE SIGNATURE',
        certificationText: 'I certify that all answers marked on this sheet are my own.',
        heightMm: 15,
        typography: { ...DEFAULT_TYPOGRAPHY, fontSizePt: 8.5, fontWeight: 700, textColor: '#475569' },
      };
  }
}

export const COMPONENT_SUITE_CSS = `/* NEXIAM OMR Component Suite — shared CSS contract (v1) */
.omr-header { border-bottom: 2px solid #0f172a; padding-bottom: 3px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: flex-end; }
.omr-title h1 { font-size: 13pt; font-weight: 900; text-transform: uppercase; line-height: 1.1; color: #0f172a; }
.omr-title p { font-size: 7pt; color: #475569; font-weight: 600; text-transform: uppercase; }
.omr-sheet-code { font-family: monospace; font-size: 8pt; font-weight: 800; border: 1px solid #0f172a; padding: 2px 5px; border-radius: 2px; }
.omr-top-section { display: grid; grid-template-columns: 1fr 150px; gap: 10px; margin-bottom: 8px; }
.omr-field-box { border: 1.2px solid #334155; border-radius: 3px; padding: 3px 6px; background: #f8fafc; }
.omr-field-label { font-size: 6.5pt; font-weight: 800; text-transform: uppercase; color: #475569; }
.omr-field-line { border-bottom: 1px dashed #94a3b8; height: 11px; }
.omr-instructions { border: 1px solid #cbd5e1; border-radius: 3px; padding: 3px 6px; background: #ffffff; }
.omr-id-container { border: 1.5px solid #0f172a; border-radius: 3px; padding: 4px 5px; background: #fafafa; }
.omr-id-box { width: 11px; height: 13px; border: 1.2px solid #0f172a; border-radius: 2px; background: #ffffff; }
.omr-id-bub, .omr-bub { border: 1.1px solid #1e293b; border-radius: 50%; background: #ffffff; }
.omr-id-bub { width: 11px; height: 8.5px; }
.omr-bub { width: 9px; height: 8.5px; }
.omr-exam-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(28mm, 1fr)); gap: 5px; border-top: 1.5px solid #0f172a; padding-top: 5px; }
.omr-subject-header { font-size: 6pt; font-weight: 900; background: #0f172a; color: #ffffff; padding: 2px 3px; border-radius: 2px; text-align: center; text-transform: uppercase; }
.omr-option-header { display: flex; justify-content: flex-end; gap: 3.5px; font-size: 5.5pt; font-weight: 900; }
.omr-q-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2px; }
.omr-q-num { font-size: 6pt; font-weight: 700; font-family: monospace; color: #334155; width: 11px; text-align: right; padding-right: 2px; }`;

export const COMPONENT_SUITE_PROMPT = `You are reproducing a NEXIAM OMR answer sheet using the following component suite contract.

SUITE COMPONENTS:
1. Document Header — exam title, subtitle, form code badge. Typography: fontSizePt, fontFamily, fontWeight, textColor, textAlign.
2. Candidate Info — candidate name write-in line, birthdate digit boxes (MM/DD/YYYY), room/seat write-in line.
3. Instructions — bordered notice box with pencil shading rules and mark examples.
4. Examinee ID Matrix (QTYPE_INT) — N digit write-in boxes on top, vertical 0–9 bubble columns below each.
5. Question Grid (QTYPE_MCQ) — numbered question rows, 4/5 option bubbles (A–D / A–E), column-top option headers, configurable bubble pitch (X) and row pitch (Y) in millimetres.
6. Custom Text — free-positioned text label with full typography controls.
7. Signature Box — signature line plus certification text.

GEOMETRY CONTRACT: single A4 page (210mm x 297mm), print-safe margins, all bubbles >= 4.5mm diameter, bubble pitch >= 5.76mm, row pitch >= 3.5mm. Option letters are placed as column headers, never inside the circles.

OUTPUT: print-exact static HTML/CSS with the suite CSS classes below. Do not add scripts, remote assets, or interactive JS.

${COMPONENT_SUITE_CSS}`;
