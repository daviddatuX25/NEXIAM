import { describe, it, expect, beforeEach } from 'vitest';
import {
  createComponent,
  renderFullSheetHtml,
  componentToSectionData,
  componentBlockSizeMm,
  gridOptionLabels,
  gridBlockWidthMm,
  gridBlockHeightMm,
  rollBlockWidthMm,
  rollBlockHeightMm,
  resetComponentSeq,
  COMPONENT_PALETTE,
  PRESET_150Q_COMPONENTS,
} from '../../resources/js/lib/omrComponents';

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
      expect(COMPONENT_PALETTE).toHaveLength(9);

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
      expect((comp as any).label).toContain('Candidate Name');
    });
  });

  describe('renderFullSheetHtml (Atomicity Contract)', () => {
    it('renders a completely blank sheet when components array is empty', () => {
      const html = renderFullSheetHtml([]);
      expect(html).toContain('<!DOCTYPE html>');
      expect(getBodyContent(html)).toBe('');
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
      const roll = createComponent('roll') as any;
      expect(rollBlockWidthMm(roll)).toBeGreaterThan(30);
      expect(rollBlockHeightMm(roll)).toBeGreaterThan(40);

      const grid = createComponent('question_grid') as any;
      expect(gridOptionLabels(grid)).toEqual(['A', 'B', 'C', 'D']);
      expect(gridBlockWidthMm(grid)).toBeGreaterThan(20);
      expect(gridBlockHeightMm(grid)).toBeGreaterThan(100);

      const size = componentBlockSizeMm(grid);
      expect(size.w_mm).toBe(gridBlockWidthMm(grid));
      expect(size.h_mm).toBe(gridBlockHeightMm(grid));
    });
  });
});
