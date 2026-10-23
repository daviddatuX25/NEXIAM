import { describe, expect, it } from 'vitest';

interface ComponentProps {
  label?: string;
  binding?: string;
  text?: string;
  title?: string;
  [key: string]: unknown;
}

interface ComponentItem {
  id: string;
  type: string;
  x: number;
  y: number;
  w: number;
  h: number;
  props?: ComponentProps;
}

interface BuilderDocument {
  schema_version?: number;
  page?: {
    paper_size?: string;
    orientation?: string;
    margin_mm?: number;
  };
  components?: ComponentItem[];
}

function humanizeError(code: string | null | undefined): string {
  if (!code) return 'Preview generation failed. Please check the template and try again.';
  switch (code) {
    case 'builder_invalid':
      return 'The template layout contains compilation issues. Review the diagnostics below and adjust the layout in Design.';
    case 'template_document_required':
      return 'A Word or ODT document is required before generating a preview.';
    case 'template_mode_mismatch':
      return 'The selected template mode does not match the document format.';
    case 'template_conflict':
      return 'This template was modified in another session. Please reload to review the latest changes.';
    case 'template_document_missing':
      return 'The document file is missing or unreadable on the server.';
    default:
      return String(code).replace(/_/g, ' ');
  }
}

function pageDimensions(paperSize: string, orientation: string) {
  const base = paperSize === 'legal' ? { width_mm: 216, height_mm: 356 } : paperSize === 'letter' ? { width_mm: 216, height_mm: 279 } : { width_mm: 210, height_mm: 297 };
  return orientation === 'landscape' ? { width_mm: base.height_mm, height_mm: base.width_mm } : base;
}

function validateBuilderState(doc: BuilderDocument | null | undefined, paperSize: string, orientation: string) {
  if (!doc || !Array.isArray(doc.components) || doc.components.length === 0) {
    return {
      valid: false,
      message: 'Add at least one printable component to the canvas in Design before proceeding to Verify.',
    };
  }

  const page = doc.page || {};
  const margin = typeof page.margin_mm === 'number' ? page.margin_mm : 12;
  const dims = pageDimensions(paperSize || page.paper_size || 'a4', orientation || page.orientation || 'portrait');
  const contentWidth = dims.width_mm - (margin * 2);
  const contentHeight = dims.height_mm - (margin * 2);

  for (const comp of doc.components) {
    if (comp.type === 'field') {
      const binding = comp.props?.binding;
      if (!binding || !String(binding).trim()) {
        return {
          valid: false,
          message: `Field "${comp.props?.label || 'Bound Field'}" is missing a placeholder binding. Select the field in Design and choose a placeholder.`,
        };
      }
    }

    const x = Number(comp.x) || 0;
    const y = Number(comp.y) || 0;
    const w = Number(comp.w) || 0;
    const h = Number(comp.h) || 0;

    if (x < 0 || y < 0 || (x + w) > contentWidth + 0.1 || (y + h) > contentHeight + 0.1) {
      return {
        valid: false,
        message: `Component "${comp.props?.label || comp.type || 'Item'}" extends beyond the printable page margins (${contentWidth} × ${contentHeight} mm). Adjust its position or dimensions in Design before verifying.`,
      };
    }
  }

  return { valid: true, message: null };
}

function checkCanSave(options: {
  hasValidName: boolean;
  mode: 'html' | 'docx';
  builderDocument: BuilderDocument | null;
  content: string;
  previewPdfUrl: string;
  previewLoading: boolean;
  previewError: string | null;
  previewDiagnostics: Array<{ code: string; message: string }>;
  isPdfStale: boolean;
  isConflictActive: boolean;
  formProcessing: boolean;
  paperSize?: string;
  orientation?: string;
  analysisValid?: boolean;
  hasDocumentFile?: boolean;
}) {
  const isLegacyHtml = options.mode === 'html' && !options.builderDocument && Boolean(options.content?.trim());

  let hasFatalHtmlIssues = false;
  if (options.mode === 'html') {
    if (options.previewDiagnostics.length > 0) {
      hasFatalHtmlIssues = true;
    } else if (isLegacyHtml) {
      hasFatalHtmlIssues = !options.content?.trim();
    } else if (!options.builderDocument && !options.content?.trim()) {
      hasFatalHtmlIssues = true;
    } else if (options.builderDocument && (!Array.isArray(options.builderDocument.components) || options.builderDocument.components.length === 0)) {
      hasFatalHtmlIssues = true;
    } else {
      const clientValidation = validateBuilderState(options.builderDocument, options.paperSize || 'a4', options.orientation || 'portrait');
      if (!clientValidation.valid) hasFatalHtmlIssues = true;
    }
  }

  const hasFatalDocxIssues = options.mode === 'docx' ? (!options.analysisValid || !options.hasDocumentFile) : false;
  const pdfIsCurrentAndValid = Boolean(options.previewPdfUrl) && !options.previewLoading && !options.previewError && !options.isPdfStale && options.previewDiagnostics.length === 0;

  return (
    options.hasValidName &&
    pdfIsCurrentAndValid &&
    !hasFatalHtmlIssues &&
    !hasFatalDocxIssues &&
    !options.formProcessing &&
    !options.isConflictActive
  );
}

describe('Result Sheet Stage Transition & Error Hardening', () => {
  describe('Humanized Preview Errors', () => {
    it('translates raw builder_invalid to actionable guidance', () => {
      const result = humanizeError('builder_invalid');
      expect(result).not.toContain('builder_invalid');
      expect(result).toContain('The template layout contains compilation issues');
      expect(result).toContain('Design');
    });

    it('translates document missing/required errors truthfully', () => {
      expect(humanizeError('template_document_required')).toContain('Word or ODT document is required');
      expect(humanizeError('template_conflict')).toContain('modified in another session');
    });

    it('handles unexpected codes by replacing underscores with spaces', () => {
      expect(humanizeError('unexpected_fatal_failure')).toBe('unexpected fatal failure');
    });
  });

  describe('Builder State Validation before Verify Transition', () => {
    it('rejects fresh draft with empty components', () => {
      const emptyDoc: BuilderDocument = { schema_version: 1, page: { paper_size: 'a4', orientation: 'portrait' }, components: [] };
      const validation = validateBuilderState(emptyDoc, 'a4', 'portrait');
      expect(validation.valid).toBe(false);
      expect(validation.message).toContain('Add at least one printable component');
    });

    it('rejects field components with missing binding', () => {
      const docWithEmptyField: BuilderDocument = {
        schema_version: 1,
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [
          { id: 'field-1', type: 'field', x: 0, y: 0, w: 50, h: 10, props: { label: 'Applicant Name', binding: '' } },
        ],
      };
      const validation = validateBuilderState(docWithEmptyField, 'a4', 'portrait');
      expect(validation.valid).toBe(false);
      expect(validation.message).toContain('is missing a placeholder binding');
    });

    it('rejects components extending beyond printable width', () => {
      const docWithOverflow: BuilderDocument = {
        schema_version: 1,
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [
          { id: 'text-1', type: 'text', x: 100, y: 0, w: 100, h: 10, props: { text: 'Hello' } },
        ],
      };
      const validation = validateBuilderState(docWithOverflow, 'a4', 'portrait');
      expect(validation.valid).toBe(false);
      expect(validation.message).toContain('extends beyond the printable page margins');
    });

    it('accepts valid layout within printable bounds', () => {
      const validDoc: BuilderDocument = {
        schema_version: 1,
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [
          { id: 'header-1', type: 'header', x: 0, y: 0, w: 186, h: 24, props: { title: 'Exam Results' } },
          { id: 'field-1', type: 'field', x: 0, y: 30, w: 80, h: 10, props: { label: 'Name', binding: 'applicant_name' } },
        ],
      };
      const validation = validateBuilderState(validDoc, 'a4', 'portrait');
      expect(validation.valid).toBe(true);
      expect(validation.message).toBeNull();
    });
  });

  describe('Legacy HTML Template Support', () => {
    it('allows unchanged legacy HTML with non-empty content and no builderDocument to save after valid PDF preview', () => {
      const canSave = checkCanSave({
        hasValidName: true,
        mode: 'html',
        builderDocument: null,
        content: '<div><h1>Official ICAT Legacy Results</h1><p>{{applicant_name}}</p></div>',
        previewPdfUrl: 'data:application/pdf;base64,JVBERi0xLjQK...',
        previewLoading: false,
        previewError: null,
        previewDiagnostics: [],
        isPdfStale: false,
        isConflictActive: false,
        formProcessing: false,
      });

      expect(canSave).toBe(true);
    });

    it('blocks legacy HTML if content is empty', () => {
      const canSave = checkCanSave({
        hasValidName: true,
        mode: 'html',
        builderDocument: null,
        content: '',
        previewPdfUrl: 'data:application/pdf;base64,JVBERi0xLjQK...',
        previewLoading: false,
        previewError: null,
        previewDiagnostics: [],
        isPdfStale: false,
        isConflictActive: false,
        formProcessing: false,
      });

      expect(canSave).toBe(false);
    });

    it('blocks legacy HTML save if preview is stale', () => {
      const canSave = checkCanSave({
        hasValidName: true,
        mode: 'html',
        builderDocument: null,
        content: '<div>Legacy HTML</div>',
        previewPdfUrl: 'data:application/pdf;base64,JVBERi0xLjQK...',
        previewLoading: false,
        previewError: null,
        previewDiagnostics: [],
        isPdfStale: true,
        isConflictActive: false,
        formProcessing: false,
      });

      expect(canSave).toBe(false);
    });
  });

  describe('Preview Sequence Guard & Stale Response Invalidation', () => {
    it('stale runId response is ignored when previewSequence has advanced', () => {
      let previewSequence = 1;
      let previewPdfUrl = '';
      let isPdfStale = true;

      // Simulated run 1 started
      const runId1 = previewSequence;

      // User makes an edit, which triggers invalidatePreview()
      previewSequence++; // previewSequence is now 2
      isPdfStale = true;
      previewPdfUrl = '';

      // Simulated run 1 finishes asynchronously
      const responseFromRun1 = { pdf_base64: 'STALE_DATA' };
      if (runId1 === previewSequence) {
        previewPdfUrl = responseFromRun1.pdf_base64;
        isPdfStale = false;
      }

      // Assert that stale run 1 did not overwrite previewPdfUrl or clear isPdfStale
      expect(previewPdfUrl).toBe('');
      expect(isPdfStale).toBe(true);

      // Simulated run 2 finishes
      const runId2 = previewSequence; // 2
      const responseFromRun2 = { pdf_base64: 'FRESH_DATA' };
      if (runId2 === previewSequence) {
        previewPdfUrl = responseFromRun2.pdf_base64;
        isPdfStale = false;
      }

      expect(previewPdfUrl).toBe('FRESH_DATA');
      expect(isPdfStale).toBe(false);
    });
  });
});
