// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});
import {
    createComponent,
    renderFullSheetHtml,
} from '../../resources/js/lib/omrComponents';
import {
    emitNativeMapping,
    emitOverlayBoxes,
    validateStudioLayout,
} from '../../resources/js/lib/omrStudioGeometry';

describe('OMR multipage authoring contracts', () => {
    it('renders stable fixed-size page containers and scopes components by page', () => {
        const first = createComponent('custom_text', { pageNo: 1 });
        const second = createComponent('custom_text', { pageNo: 2 });
        first.text = 'FIRST PAGE';
        second.text = 'SECOND PAGE';

        const html = renderFullSheetHtml(
            [second, first],
            undefined,
            { showFiducials: false, qr: { visible: false } },
            2,
        );

        expect(html.match(/class="omr-sheet"/g)).toHaveLength(2);
        expect(html.indexOf('data-page-no="1"')).toBeLessThan(
            html.indexOf('data-page-no="2"'),
        );
        expect(html.indexOf('FIRST PAGE')).toBeLessThan(
            html.indexOf('SECOND PAGE'),
        );
    });

    it('carries page identity into mapping and overlay payloads', () => {
        const mapping = emitNativeMapping(
            210,
            297,
            [
                {
                    id: 'Questions_2',
                    type: 'mcq',
                    page_no: 2,
                    x_mm: 15,
                    y_mm: 84,
                    w_mm: 35,
                    h_mm: 80,
                    questions: 2,
                    options: 4,
                    start_q: 3,
                },
            ],
            2,
        );
        const overlay = emitOverlayBoxes(
            [
                {
                    id: 'Header_2',
                    type: 'header',
                    page_no: 2,
                    x_mm: 15,
                    y_mm: 15,
                    w_mm: 180,
                    h_mm: 15,
                },
            ],
            210,
            297,
        );

        expect(mapping.page_count).toBe(2);
        expect(mapping.blocks[0].page_no).toBe(2);
        expect(mapping.blocks[0].labels).toEqual(['q3', 'q4']);
        expect(overlay.boxes[0].page_no).toBe(2);
    });

    it('does not report overlap for identical geometry on different pages', () => {
        const result = validateStudioLayout(
            { width_mm: 210, height_mm: 297, margin_mm: 15 },
            { top_mm: 12, right_mm: 12, bottom_mm: 14, left_mm: 12 },
            [
                {
                    id: 'Page_1',
                    type: 'mcq',
                    page_no: 1,
                    x_mm: 20,
                    y_mm: 80,
                    w_mm: 30,
                    h_mm: 80,
                },
                {
                    id: 'Page_2',
                    type: 'mcq',
                    page_no: 2,
                    x_mm: 20,
                    y_mm: 80,
                    w_mm: 30,
                    h_mm: 80,
                },
            ],
        );

        expect(result.valid).toBe(true);
        expect(result.warnings).not.toContainEqual(
            expect.objectContaining({ code: 'block_collision' }),
        );
    });

    it('isolates components for active page single-page canvas HTML preview', () => {
        const page1Component = createComponent('custom_text', { pageNo: 1 });
        const page2Component = createComponent('custom_text', { pageNo: 2 });
        page1Component.text = 'FIRST PAGE ONLY';
        page2Component.text = 'SECOND PAGE ONLY';

        const allComponents = [page1Component, page2Component];

        // Page 1 canvas HTML preview:
        const p1Components = allComponents.filter(
            (c) => (Number(c.page_no) || 1) === 1,
        );
        const p1Html = renderFullSheetHtml(
            p1Components.map((c) => ({ ...c, page_no: 1 })),
            undefined,
            { showFiducials: false, qr: { visible: false } },
            1,
        );
        expect(p1Html).toContain('FIRST PAGE ONLY');
        expect(p1Html).not.toContain('SECOND PAGE ONLY');
        expect(p1Html.match(/class="omr-sheet"/g)).toHaveLength(1);

        // Page 2 canvas HTML preview:
        const p2Components = allComponents.filter(
            (c) => (Number(c.page_no) || 1) === 2,
        );
        const p2Html = renderFullSheetHtml(
            p2Components.map((c) => ({ ...c, page_no: 1 })),
            undefined,
            { showFiducials: false, qr: { visible: false } },
            1,
        );
        expect(p2Html).toContain('SECOND PAGE ONLY');
        expect(p2Html).not.toContain('FIRST PAGE ONLY');
        expect(p2Html.match(/class="omr-sheet"/g)).toHaveLength(1);
    });

    it('distributes aptitude areas evenly across multi-page drafts with contiguous startQ', () => {
        const areas = [
            { code: 'LOGIC', name: 'Logic' },
            { code: 'VERBAL', name: 'Verbal' },
            { code: 'QUANT', name: 'Quantitative' },
            { code: 'SPATIAL', name: 'Spatial' },
            { code: 'MEMORY', name: 'Memory' },
            { code: 'LANGUAGE', name: 'Language' },
        ];
        const pageIndexMap = new Map();
        let startQ = 1;
        const grids2P = areas.map((area, idx) => {
            const qCount = 25;
            const targetPage = Math.min(2, Math.floor((idx * 2) / areas.length) + 1);
            const indexOnPage = pageIndexMap.get(targetPage) ?? 0;
            pageIndexMap.set(targetPage, indexOnPage + 1);
            const grid = {
                id: `Part_${idx + 1}`,
                page_no: targetPage,
                startQ,
                questionsCount: qCount,
                posX: 10 + indexOnPage * 31,
            };
            startQ += qCount;
            return grid;
        });

        expect(grids2P[0].page_no).toBe(1);
        expect(grids2P[0].startQ).toBe(1);
        expect(grids2P[1].page_no).toBe(1);
        expect(grids2P[1].startQ).toBe(26);
        expect(grids2P[2].page_no).toBe(1);
        expect(grids2P[2].startQ).toBe(51);

        expect(grids2P[3].page_no).toBe(2);
        expect(grids2P[3].startQ).toBe(76);
        expect(grids2P[3].posX).toBe(10); // Starts on column 1 of page 2
        expect(grids2P[4].page_no).toBe(2);
        expect(grids2P[4].startQ).toBe(101);
        expect(grids2P[5].page_no).toBe(2);
        expect(grids2P[5].startQ).toBe(126);

        // 4-page distribution utilizes all 4 pages
        const pageIndexMap4P = new Map();
        const grids4P = areas.map((area, idx) => {
            const targetPage = Math.min(4, Math.floor((idx * 4) / areas.length) + 1);
            const indexOnPage = pageIndexMap4P.get(targetPage) ?? 0;
            pageIndexMap4P.set(targetPage, indexOnPage + 1);
            return { page_no: targetPage, posX: 10 + indexOnPage * 31 };
        });
        const usedPages = new Set(grids4P.map((g) => g.page_no));
        expect(usedPages).toEqual(new Set([1, 2, 3, 4]));
    });
});
describe('OMR multipage photo upload lifecycle', () => {
    const studioProps = () => ({
        draft: {
            id: 42,
            can_edit: true,
            stage: 'draft',
            source_kind: 'image',
            requested_page_count: 2,
            lock_version: 1,
            authoring: { studio: { stage1Mode: 'upload' }, pages: [] },
        },
        urls: {
            studio: '/studio',
            source_ingest: '/ingest',
            source_rectify: '/rectify',
            confirm_pages: '/confirm-pages',
        },
    });

    function stubPhotoBrowserApis() {
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn().mockResolvedValue({
                width: 1200,
                height: 1700,
                close: vi.fn(),
            }),
        );
        vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:answer-sheet');
        vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    }

    it('allows selecting multiple page photos and rectifies them sequentially with page-number payloads', async () => {
        stubPhotoBrowserApis();
        const rectifyForms: FormData[] = [];
        vi.stubGlobal(
            'fetch',
            vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
                if (String(input) === '/studio') {
                    return Response.json({ lock_version: 2 }, { status: 200 });
                }
                if (String(input) === '/rectify') {
                    const form = init?.body as FormData;
                    rectifyForms.push(form);
                    const pageNo = Number(form.get('page_no'));
                    const pages = rectifyForms.map((_, index) => ({
                        page_no: index + 1,
                        asset_id: `page-${index + 1}`,
                        confirmed: false,
                    }));
                    return Response.json(
                        {
                            lock_version: 2 + pageNo,
                            page_no: pageNo,
                            pages,
                            preview_url: `/preview/page-${pageNo}`,
                        },
                        { status: 200 },
                    );
                }
                throw new Error(`Unexpected request: ${String(input)}`);
            }),
        );

        const view = render(OmrTemplateStudio, studioProps());
        const input = view.container.querySelector(
            'input[type="file"]',
        ) as HTMLInputElement;
        expect(input.multiple).toBe(true);
        expect(input.accept).toBe('.pdf,.docx,.doc,.png,.jpg,.jpeg,.webp');
        expect(view.getByText('Upload Answer Sheet (2 Pages)')).not.toBeNull();
        expect(
            view.getByText(/You can select 2 photos at once to autocrop both pages sequentially/),
        ).not.toBeNull();

        const first = new File(['page one'], 'page-1.jpg', { type: 'image/jpeg' });
        const second = new File(['page two'], 'page-2.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input, 'files', {
            configurable: true,
            value: [first, second],
        });
        await fireEvent.change(input);

        await waitFor(() =>
            expect(view.getByRole('button', { name: /Straighten/ }).textContent).toContain(
                'Continue to Page 2',
            ),
        );
        await fireEvent.click(
            view.getByRole('button', { name: /Straighten.*Continue to Page 2/ }),
        );
        await waitFor(() =>
            expect(view.getByRole('heading', { name: /Page 2 of 2/ })).not.toBeNull(),
        );
        expect(rectifyForms[0].get('page_no')).toBe('1');
        expect((rectifyForms[0].get('source') as File).name).toBe('page-1.jpg');

        await waitFor(() =>
            expect(view.getByRole('button', { name: /Straighten/ }).textContent).toBe(
                'Straighten Page 2',
            ),
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Straighten Page 2' }),
        );
        await waitFor(() =>
            expect(view.getByRole('button', { name: 'Confirm 2 pages' })).not.toBeNull(),
        );
        expect(rectifyForms.map((form) => form.get('page_no'))).toEqual(['1', '2']);
        expect((rectifyForms[1].get('source') as File).name).toBe('page-2.jpg');
        expect(
            (view.getByRole('button', { name: 'Confirm 2 pages' }) as HTMLButtonElement)
                .disabled,
        ).toBe(false);
    });

    it('keeps page confirmation disabled and explains how to supply a missing page', async () => {
        stubPhotoBrowserApis();
        vi.stubGlobal(
            'fetch',
            vi.fn(async (input: RequestInfo | URL) => {
                if (String(input) === '/studio') {
                    return Response.json({ lock_version: 2 }, { status: 200 });
                }
                if (String(input) === '/rectify') {
                    return Response.json(
                        {
                            lock_version: 3,
                            page_no: 1,
                            pages: [{ page_no: 1, asset_id: 'page-1', confirmed: false }],
                            preview_url: '/preview/page-1',
                        },
                        { status: 200 },
                    );
                }
                throw new Error(`Unexpected request: ${String(input)}`);
            }),
        );

        const view = render(OmrTemplateStudio, studioProps());
        const input = view.container.querySelector(
            'input[type="file"]',
        ) as HTMLInputElement;
        const onlyPage = new File(['page one'], 'page-1.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input, 'files', {
            configurable: true,
            value: [onlyPage],
        });
        await fireEvent.change(input);
        await fireEvent.click(await view.findByRole('button', { name: 'Straighten Page 1' }));

        expect(
            await view.findByText(
                '1 of 2 pages straightened. Close this review and select or photograph the next page.',
            ),
        ).not.toBeNull();
        expect(
            (view.getByRole('button', { name: 'Confirm 1 pages' }) as HTMLButtonElement)
                .disabled,
        ).toBe(true);
    });
});
