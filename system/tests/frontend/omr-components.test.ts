import { describe, it, expect, beforeEach } from 'vitest';
import {
  createComponent,
  renderFullSheetHtml,
  componentToSectionData,
  componentBlockSizeMm,
  canvasSectionBoundsMm,
  canvasComponentBlockSizeMm,
  gridOptionLabels,
  gridBlockWidthMm,
  gridBlockHeightMm,
  rollBlockWidthMm,
  rollBlockHeightMm,
  canvasRollBlockHeightMm,
  canvasRollBlockWidthMm,
  minimumCanvasPitchMm,
  resetComponentSeq,
  COMPONENT_PALETTE,
  paletteForPath,
  overlayPaletteForPath,
  COMPONENT_PATH_FILTERS,
  isQuestionGridComponent,
  PRESET_150Q_COMPONENTS,
} from '../../resources/js/lib/omrComponents';
import { createStudioHistory } from '../../resources/js/lib/studioHistory';

function getBodyContent(html: string): string {
  const match = html.match(/<body>([\s\S]*?)<\/body>/);
  return match ? match[1].trim() : '';
}

describe('Universal OMR Component Suite', () => {
  beforeEach(() => {
    resetComponentSeq();
  });

  describe('createComponent', () => {
    it('creates every palette component kind with atomic defaults and geometry', () => {
      expect(COMPONENT_PALETTE).toHaveLength(10);

      const kinds = COMPONENT_PALETTE.map((p) => p.kind);
      for (const kind of kinds) {
        const comp = createComponent(kind);
        expect(comp.id).toBeDefined();
        expect(comp.type).toBe(kind === 'question_grid' ? 'mcq' : kind);
        expect(typeof comp.posX).toBe('number');
        expect(typeof comp.posY).toBe('number');
        expect(typeof comp.w_mm).toBe('number');
        expect(typeof comp.h_mm).toBe('number');
        expect(comp.isLocked).toBe(false);
      }
    });

    it('respects custom position on creation', () => {
      const comp = createComponent('candidate_name', { posX: 25, posY: 45 });
      expect(comp.posX).toBe(25);
      expect(comp.posY).toBe(45);
      expect(comp.type).toBe('candidate_name');
      expect('label' in comp ? comp.label : '').toContain('Candidate Name');
    });
    it('creates a labeled custom input with a separate response area contract', () => {
      const input = createComponent('custom_input');
      expect(input.type).toBe('custom_input');
      expect(input.label).toBe('Custom Response');
      expect(input.inputStyle).toBe('box');
      expect(input.inputKind).toBe('text');
      expect(input.h_mm).toBeGreaterThan(12);
    });
  });

  describe('path-filtered shared palette', () => {
    it('excludes question and bubble components from upload documents', () => {
      const uploadKinds = paletteForPath('upload_document').map((item) => item.kind);
      const sheetKinds = paletteForPath('sheet_designer').map((item) => item.kind);

      expect(uploadKinds).not.toContain('roll');
      expect(uploadKinds).not.toContain('question_grid');
      expect(uploadKinds).not.toContain('custom_input');
      expect(sheetKinds).toContain('roll');
      expect(sheetKinds).toContain('question_grid');
      expect(sheetKinds).toContain('custom_input');
      expect(COMPONENT_PATH_FILTERS.upload_document.includeQuestionComponents).toBe(false);
      expect(overlayPaletteForPath('upload_document').map((item) => item.kind)).toEqual(['qr_code', 'margins', 'markers']);
    });
  });

  describe('renderFullSheetHtml (Atomicity Contract)', () => {
    it('renders a completely blank sheet when components array is empty', () => {
      const html = renderFullSheetHtml([]);
      expect(html).toContain('<!DOCTYPE html>');
      expect(getBodyContent(html)).toBe('');
    });
    it('renders enabled fiducials and QR reservation in compiled HTML', () => {
      const header = createComponent('header');
      const html = renderFullSheetHtml(
        [header],
        { paperWidthMm: 210, paperHeightMm: 297, marginMm: 15, markerPattern: 'target' },
        {
          showFiducials: true,
          qr: { visible: true, xMm: 162, yMm: 18, sizeMm: 34, overlayTag: 'blank_instance_qr' },
        },
      );
      const body = getBodyContent(html);

      expect(body.match(/omr-fiducial-target/g)).toHaveLength(4);
      expect(body).toContain('data-omr-overlay="blank_instance_qr"');
      expect(body).toContain('data-omr-qr-size-mm="34"');
      expect(body).toContain('width: 34mm');
      expect(body).toContain('height: 34mm');
    });
    it('renders field labels and configurable input regions separately', () => {
      const field = createComponent('candidate_name');
      if (field.type !== 'candidate_name') throw new Error('Expected candidate field');
      field.label = 'Name';
      field.inputStyle = 'box';
      field.inputKind = 'text';
      field.backgroundColor = '#000000';
      const custom = createComponent('custom_input');
      const html = renderFullSheetHtml([field, custom]);
      const body = getBodyContent(html);

      expect(body).toContain('Name');
      expect(body).toContain('omr-field-input-box');
      expect(body).not.toContain('omr-field-line');
      expect(body).toContain('data-input-kind="text"');
      expect(body).toContain('background-color: #000000');
    });
    it('renders configurable question-grid bubble size and label font size', () => {
      const grid = createComponent('question_grid');
      if (!isQuestionGridComponent(grid)) throw new Error('Expected question grid component');
      grid.bubbleDiameterMm = 6;
      grid.labelFontSizePt = 9;
      const body = getBodyContent(renderFullSheetHtml([grid]));

      expect(body).toContain('width: 6mm; height: 6mm;');
      expect(body).toContain('font-size: 9pt;');
    });

    it('renders ONLY the header when only a Document Header component is added', () => {
      const header = createComponent('header');
      const html = renderFullSheetHtml([header]);
      const body = getBodyContent(html);

      expect(body).toContain('omr-header');
      expect(body).toContain('EXAMINATION ANSWER SHEET');
      expect(body).toContain('FORM 1-A');

      // Crucial acceptance: must NOT render any candidate info, birthdate, room/seat, instructions, ID matrix, or grids!
      expect(body).not.toContain('omr-field-box');
      expect(body).not.toContain('Candidate Name');
      expect(body).not.toContain('Birthdate');
      expect(body).not.toContain('Room / Seat');
      expect(body).not.toContain('omr-instructions');
      expect(body).not.toContain('omr-id-container');
      expect(body).not.toContain('omr-grid-block');
    });

    it('renders ONLY instructions when only an Instructions component is added', () => {
      const instructions = createComponent('instructions');
      const html = renderFullSheetHtml([instructions]);
      const body = getBodyContent(html);

      expect(body).toContain('omr-instructions');
      expect(body).toContain('Use Black Pencil/Pen');

      expect(body).not.toContain('omr-header');
      expect(body).not.toContain('omr-field-box');
      expect(body).not.toContain('omr-id-container');
      expect(body).not.toContain('omr-grid-block');
    });

    it('renders ONLY the candidate name box when only Candidate Name is added', () => {
      const name = createComponent('candidate_name');
      const html = renderFullSheetHtml([name]);
      const body = getBodyContent(html);

      expect(body).toContain('omr-field-box');
      expect(body).toContain('Candidate Name (Print');

      expect(body).not.toContain('Birthdate');
      expect(body).not.toContain('Room / Seat');
      expect(body).not.toContain('omr-header');
      expect(body).not.toContain('omr-instructions');
      expect(body).not.toContain('omr-id-container');
    });

    it('renders ONLY the question grid when only a Question Grid is added', () => {
      const grid = createComponent('question_grid');
      const html = renderFullSheetHtml([grid]);
      const body = getBodyContent(html);

      expect(body).toContain('omr-grid-block');
      expect(body).toContain('Subject');
      expect(body).toContain('omr-q-row');

      expect(body).not.toContain('omr-header');
      expect(body).not.toContain('omr-field-box');
      expect(body).not.toContain('omr-instructions');
      expect(body).not.toContain('omr-id-container');
    });

    it('renders full assembled preset when using PRESET_150Q_COMPONENTS', () => {
      const html = renderFullSheetHtml(PRESET_150Q_COMPONENTS);
      const body = getBodyContent(html);

      expect(body).toContain('University General Aptitude Test');
      expect(body).toContain('Candidate Name');
      expect(body).toContain('Birthdate');
      expect(body).toContain('Room / Seat');
      expect(body).toContain('omr-instructions');
      expect(body).toContain('Examinee Number');
      expect(body).toContain('1. English');
      expect(body).toContain('6. Logic');
    });
  });

  describe('componentToSectionData (OMR Geometry Bridge)', () => {
    it('returns null for text components (HTML-only)', () => {
      expect(componentToSectionData(createComponent('header'))).toBeNull();
      expect(componentToSectionData(createComponent('candidate_name'))).toBeNull();
      expect(componentToSectionData(createComponent('birthdate'))).toBeNull();
      expect(componentToSectionData(createComponent('room_seat'))).toBeNull();
      expect(componentToSectionData(createComponent('instructions'))).toBeNull();
      expect(componentToSectionData(createComponent('custom_text'))).toBeNull();
      expect(componentToSectionData(createComponent('signature'))).toBeNull();
    });

    it('converts roll component to SectionData', () => {
      const roll = createComponent('roll');
      const sec = componentToSectionData(roll);
      expect(sec).not.toBeNull();
      expect(sec?.type).toBe('roll');
      expect(sec?.questionsCount).toBe(7);
      expect(sec?.cols).toBe(7);
      expect(sec?.optionsCount).toBe(10);
    });

    it('converts question grid to SectionData', () => {
      const grid = createComponent('question_grid');
      const sec = componentToSectionData(grid);
      expect(sec).not.toBeNull();
      expect(sec?.type).toBe('mcq');
      expect(sec?.questionsCount).toBe(25);
      expect(sec?.optionsCount).toBe(4);
      expect(sec?.startQ).toBe(1);
    });
  });

  describe('Component Sizing & Geometry', () => {
    it('computes content-derived dimensions for roll and grids', () => {
      const roll = createComponent('roll');
      if (roll.type !== 'roll') throw new Error('Expected roll component');
      expect(rollBlockWidthMm(roll)).toBeGreaterThan(30);
      expect(rollBlockHeightMm(roll)).toBeGreaterThan(40);

      const grid = createComponent('question_grid');
      if (!isQuestionGridComponent(grid)) throw new Error('Expected question grid component');
      expect(gridOptionLabels(grid)).toEqual(['A', 'B', 'C', 'D']);
      expect(gridBlockWidthMm(grid)).toBeGreaterThan(20);
      expect(gridBlockHeightMm(grid)).toBeGreaterThan(100);

      const size = componentBlockSizeMm(grid);
      expect(size.w_mm).toBeGreaterThanOrEqual(gridBlockWidthMm(grid));
      expect(size.h_mm).toBeGreaterThanOrEqual(gridBlockHeightMm(grid));
    });
    it('keeps the Stage-1 examinee matrix border around all ten rows', () => {
      const roll = createComponent('roll');
      if (roll.type !== 'roll') throw new Error('Expected roll component');

      const compactBounds = canvasComponentBlockSizeMm(roll);
      expect(minimumCanvasPitchMm()).toBe(5.2);
      expect(compactBounds.w_mm).toBe(canvasRollBlockWidthMm(roll));
      expect(compactBounds.w_mm).toBeLessThan(roll.w_mm);
      expect(compactBounds.h_mm).toBeGreaterThanOrEqual(canvasRollBlockHeightMm(roll));

      roll.rowPitchMm = 4;
      expect(canvasComponentBlockSizeMm(roll).h_mm).toBe(compactBounds.h_mm);

      roll.rowPitchMm = 12;
      const spaciousBounds = canvasComponentBlockSizeMm(roll);
      expect(spaciousBounds.h_mm).toBeGreaterThan(compactBounds.h_mm);
      expect(spaciousBounds.h_mm).toBeGreaterThanOrEqual(canvasRollBlockHeightMm(roll));
    });
    it('keeps visual bounds aligned with labels and expands with row pitch', () => {
      const compact = canvasSectionBoundsMm(4, 25, 6, 6.8);
      const spacious = canvasSectionBoundsMm(4, 25, 6, 12);
      const grid = createComponent('question_grid');

      expect(compact.w_mm).toBeGreaterThan(25);
      expect(spacious.h_mm).toBeGreaterThan(compact.h_mm);
      expect(canvasComponentBlockSizeMm(grid).w_mm).toBeGreaterThanOrEqual(compact.w_mm);
    });
  });
  describe('Studio history', () => {
    it('supports undo, redo, branch invalidation, and bounded snapshots', () => {
      const history = createStudioHistory({ count: 0 }, 2);
      history.record({ count: 1 }, 'Add component');
      history.record({ count: 2 }, 'Move component');

      expect(history.undo()).toEqual({ count: 1 });
      expect(history.redo()).toEqual({ count: 2 });

      history.undo();
      history.record({ count: 9 }, 'New branch');
      expect(history.status().canRedo).toBe(false);

      history.record({ count: 10 }, 'Second branch');
      history.record({ count: 11 }, 'Third branch');
      expect(history.status().canUndo).toBe(true);
      expect(history.undo()).toEqual({ count: 10 });
    });

    it('coalesces rapid field edits into one undo step', () => {
      const history = createStudioHistory({ value: 0 });
      history.record({ value: 1 }, 'Edit QR size', { coalesceKey: 'qr-size' });
      history.record({ value: 2 }, 'Edit QR size', { coalesceKey: 'qr-size' });

      expect(history.undo()).toEqual({ value: 0 });
      expect(history.status().canRedo).toBe(true);
    });
  });
});
