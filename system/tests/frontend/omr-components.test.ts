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
    normalizeQuestionGridRowPositions,
    resetComponentSeq,
    COMPONENT_PALETTE,
    paletteForPath,
    overlayPaletteForPath,
    COMPONENT_PATH_FILTERS,
    isQuestionGridComponent,
    PRESET_150Q_COMPONENTS,
} from '../../resources/js/lib/omrComponents';
import { createStudioHistory } from '../../resources/js/lib/studioHistory';
import {
    emitNativeMapping,
    mmToPx,
    validateStudioLayout,
} from '../../resources/js/lib/omrStudioGeometry';

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
            const comp = createComponent('candidate_name', {
                posX: 25,
                posY: 45,
            });
            expect(comp.posX).toBe(25);
            expect(comp.posY).toBe(45);
            expect(comp.type).toBe('candidate_name');
            expect('label' in comp ? comp.label : '').toContain(
                'Candidate Name',
            );
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
            const uploadKinds = paletteForPath('upload_document').map(
                (item) => item.kind,
            );
            const sheetKinds = paletteForPath('sheet_designer').map(
                (item) => item.kind,
            );

            expect(uploadKinds).not.toContain('roll');
            expect(uploadKinds).not.toContain('question_grid');
            expect(uploadKinds).not.toContain('custom_input');
            expect(sheetKinds).toContain('roll');
            expect(sheetKinds).toContain('question_grid');
            expect(sheetKinds).toContain('custom_input');
            expect(
                COMPONENT_PATH_FILTERS.upload_document
                    .includeQuestionComponents,
            ).toBe(false);
            expect(
                overlayPaletteForPath('upload_document').map(
                    (item) => item.kind,
                ),
            ).toEqual(['qr_code', 'margins', 'markers']);
        });
    });

    describe('renderFullSheetHtml (Atomicity Contract)', () => {
        it('renders a completely blank sheet when components array is empty', () => {
            const html = renderFullSheetHtml([]);
            expect(html).toContain('<!DOCTYPE html>');
            expect(getBodyContent(html).replace(/<section class="omr-sheet" data-page-no="\d+">\s*<\/section>/g, '').trim()).toBe('');
        });
        it('renders enabled fiducials and QR reservation in compiled HTML', () => {
            const header = createComponent('header');
            const html = renderFullSheetHtml(
                [header],
                {
                    paperWidthMm: 210,
                    paperHeightMm: 297,
                    marginMm: 23,
                    markerPattern: 'target',
                    markerInsetsMm: {
                        top: 12,
                        right: 13,
                        bottom: 14,
                        left: 15,
                    },
                },
                {
                    showFiducials: true,
                    qr: {
                        visible: true,
                        xMm: 162,
                        yMm: 18,
                        sizeMm: 34,
                        overlayTag: 'blank_instance_qr',
                    },
                },
            );
            const body = getBodyContent(html);

            expect(body.match(/omr-fiducial-target/g)).toHaveLength(4);
            expect(body).toContain('top: 12mm; left: 15mm');
            expect(body).toContain('top: 12mm; right: 13mm');
            expect(body).toContain('bottom: 14mm; left: 15mm');
            expect(body).toContain('bottom: 14mm; right: 13mm');
            expect(body).not.toContain('top: 23mm');
            expect(body).toContain('data-omr-overlay="blank_instance_qr"');
            expect(body).toContain('data-omr-qr-size-mm="34"');
            expect(body).toContain('width: 34mm');
            expect(body).toContain('height: 34mm');
        });
        it('renders field labels and configurable input regions separately', () => {
            const field = createComponent('candidate_name');
            if (field.type !== 'candidate_name')
                throw new Error('Expected candidate field');
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
            if (!isQuestionGridComponent(grid))
                throw new Error('Expected question grid component');
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
            expect(
                componentToSectionData(createComponent('header')),
            ).toBeNull();
            expect(
                componentToSectionData(createComponent('candidate_name')),
            ).toBeNull();
            expect(
                componentToSectionData(createComponent('birthdate')),
            ).toBeNull();
            expect(
                componentToSectionData(createComponent('room_seat')),
            ).toBeNull();
            expect(
                componentToSectionData(createComponent('instructions')),
            ).toBeNull();
            expect(
                componentToSectionData(createComponent('custom_text')),
            ).toBeNull();
            expect(
                componentToSectionData(createComponent('signature')),
            ).toBeNull();
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
            if (grid.type !== 'mcq')
                throw new Error('Expected question grid component');
            grid.bubbleDiameterMm = 6;
            const sec = componentToSectionData(grid);
            expect(sec).not.toBeNull();
            expect(sec?.type).toBe('mcq');
            expect(sec?.questionsCount).toBe(25);
            expect(sec?.optionsCount).toBe(4);
            expect(sec?.startQ).toBe(1);
            expect(sec?.bubbleDiameterMm).toBe(6);
        });
        it('binds a named question grid to the catalog area across native mapping', () => {
            const grid = createComponent('question_grid');
            if (grid.type !== 'mcq')
                throw new Error('Expected question grid component');
            grid.aptitudeAreaCode = 'LOGIC';
            const section = componentToSectionData(grid);
            if (!section) throw new Error('Expected a question section');
            const native = emitNativeMapping(210, 297, [
                {
                    id: section.id,
                    type: section.type,
                    area_code: section.aptitudeAreaCode,
                    x_mm: section.posX,
                    y_mm: section.posY,
                    w_mm: section.w_mm,
                    h_mm: section.h_mm,
                    questions: section.questionsCount,
                    start_q: section.startQ,
                    options: section.optionsCount,
                },
            ]);
            expect(native.blocks[0].labels[0]).toBe('LOGIC_Q_1');
            expect(native.blocks[0].labels.at(-1)).toBe('LOGIC_Q_25');
            expect(native.blocks[0].name).toBe(section.id);
        });

        it('offsets native bubbles below visual headers and option labels', () => {
            const grid = createComponent('question_grid');
            const roll = createComponent('roll');
            const gridSection = componentToSectionData(grid);
            const rollSection = componentToSectionData(roll);
            if (!gridSection || !rollSection)
                throw new Error('Expected geometric sections');

            const mapping = emitNativeMapping(210, 297, [
                {
                    id: gridSection.id,
                    type: gridSection.type,
                    x_mm: gridSection.posX,
                    y_mm: gridSection.posY,
                    w_mm: gridSection.w_mm,
                    h_mm: gridSection.h_mm,
                    questions: gridSection.questionsCount,
                    options: gridSection.optionsCount,
                    start_q: gridSection.startQ,
                    bubble_pitch_mm: gridSection.bubblePitchMm,
                    row_pitch_mm: gridSection.rowPitchMm,
                },
                {
                    id: rollSection.id,
                    type: 'roll',
                    x_mm: rollSection.posX,
                    y_mm: rollSection.posY,
                    w_mm: rollSection.w_mm,
                    h_mm: rollSection.h_mm,
                    digits: rollSection.questionsCount,
                    bubble_pitch_mm: rollSection.bubblePitchMm,
                    row_pitch_mm: rollSection.rowPitchMm,
                },
            ]);

            expect(mapping.blocks[0].origin_x).toBe(
                mmToPx(gridSection.posX + 5),
            );
            expect(mapping.blocks[0].origin_y).toBe(
                mmToPx(gridSection.posY + 8),
            );
            expect(mapping.blocks[1].origin_x).toBe(
                mmToPx(rollSection.posX + 6.4),
            );
            expect(mapping.blocks[1].origin_y).toBe(
                mmToPx(rollSection.posY + 14),
            );
        });

        it('reflows colliding aptitude columns using their full visible footprints', () => {
            const positions = [15, 46, 76, 106, 138, 162];
            const grids = positions.map((posX, index) => {
                const grid = createComponent('question_grid');
                if (!isQuestionGridComponent(grid))
                    throw new Error('Expected question grid');
                grid.id = `Part_${index + 1}`;
                grid.posX = posX;
                grid.posY = 84;
                grid.w_mm = 28;
                return grid;
            });

            expect(normalizeQuestionGridRowPositions(grids, 15, 210, 2)).toBe(
                true,
            );
            const bounds = grids.map((grid) => ({
                x: grid.posX,
                width: canvasComponentBlockSizeMm(grid).w_mm,
            }));
            for (let index = 1; index < bounds.length; index++) {
                expect(
                    bounds[index].x -
                        (bounds[index - 1].x + bounds[index - 1].width),
                ).toBeGreaterThanOrEqual(2);
            }
            expect(bounds[0].x).toBe(15);
            expect(bounds.at(-1)!.x + bounds.at(-1)!.width).toBeLessThanOrEqual(
                195,
            );
        });
    });

    describe('Component Sizing & Geometry', () => {
        it('computes content-derived dimensions for roll and grids', () => {
            const roll = createComponent('roll');
            if (roll.type !== 'roll')
                throw new Error('Expected roll component');
            expect(rollBlockWidthMm(roll)).toBeGreaterThan(30);
            expect(rollBlockHeightMm(roll)).toBeGreaterThan(40);

            const grid = createComponent('question_grid');
            if (!isQuestionGridComponent(grid))
                throw new Error('Expected question grid component');
            expect(gridOptionLabels(grid)).toEqual(['A', 'B', 'C', 'D']);
            expect(gridBlockWidthMm(grid)).toBeGreaterThan(20);
            expect(gridBlockHeightMm(grid)).toBeGreaterThan(100);

            const size = componentBlockSizeMm(grid);
            expect(size.w_mm).toBeGreaterThanOrEqual(gridBlockWidthMm(grid));
            expect(size.h_mm).toBeGreaterThanOrEqual(gridBlockHeightMm(grid));
        });
        it('keeps the Stage-1 examinee matrix border around all ten rows', () => {
            const roll = createComponent('roll');
            if (roll.type !== 'roll')
                throw new Error('Expected roll component');

            const compactBounds = canvasComponentBlockSizeMm(roll);
            expect(minimumCanvasPitchMm()).toBe(5.76);
            expect(compactBounds.w_mm).toBe(canvasRollBlockWidthMm(roll));
            expect(compactBounds.w_mm).toBeLessThan(roll.w_mm);
            expect(compactBounds.h_mm).toBeGreaterThanOrEqual(
                canvasRollBlockHeightMm(roll),
            );

            roll.rowPitchMm = 4;
            expect(canvasComponentBlockSizeMm(roll).h_mm).toBe(
                compactBounds.h_mm,
            );

            roll.rowPitchMm = 12;
            const spaciousBounds = canvasComponentBlockSizeMm(roll);
            expect(spaciousBounds.h_mm).toBeGreaterThan(compactBounds.h_mm);
            expect(spaciousBounds.h_mm).toBeGreaterThanOrEqual(
                canvasRollBlockHeightMm(roll),
            );
        });
        it('keeps visual bounds aligned with labels and expands with row pitch', () => {
            const compact = canvasSectionBoundsMm(4, 25, 6, 6.8);
            const spacious = canvasSectionBoundsMm(4, 25, 6, 12);
            const grid = createComponent('question_grid');

            expect(compact.w_mm).toBeGreaterThan(25);
            expect(spacious.h_mm).toBeGreaterThan(compact.h_mm);
            expect(
                canvasComponentBlockSizeMm(grid).w_mm,
            ).toBeGreaterThanOrEqual(compact.w_mm);
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
            history.record({ value: 1 }, 'Edit QR size', {
                coalesceKey: 'qr-size',
            });
            history.record({ value: 2 }, 'Edit QR size', {
                coalesceKey: 'qr-size',
            });

            expect(history.undo()).toEqual({ value: 0 });
            expect(history.status().canRedo).toBe(true);
        });
    });
});

describe('Recognition mapping client guard', () => {
    it('blocks overlapping guides before autosave reaches the server', () => {
        const result = validateStudioLayout(
            { width_mm: 210, height_mm: 297, margin_mm: 15 },
            { top_mm: 12, right_mm: 12, bottom_mm: 14, left_mm: 12 },
            [
                {
                    id: 'Part_1',
                    type: 'mcq',
                    x_mm: 20,
                    y_mm: 80,
                    w_mm: 30,
                    h_mm: 150,
                },
                {
                    id: 'Part_2',
                    type: 'mcq',
                    x_mm: 45,
                    y_mm: 80,
                    w_mm: 30,
                    h_mm: 150,
                },
            ],
        );

        expect(result.valid).toBe(false);
        expect(result.warnings).toContainEqual(
            expect.objectContaining({
                code: 'block_collision',
                severity: 'error',
            }),
        );
    });
});
