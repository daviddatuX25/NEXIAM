// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';

export function resolveActiveSheetImageUrl({
    activePageNo,
    canonicalPreview = null,
    rasterUrl = null,
    isHtmlMode = false,
}: {
    activePageNo: number;
    canonicalPreview?: {
        artifact?: {
            canonical_url?: string;
            pages?: Array<{ page_no: number; canonical_url: string; asset_id?: number }>;
        };
    } | null;
    rasterUrl?: string | null;
    isHtmlMode?: boolean;
}): string | null {
    if (isHtmlMode) return null;

    // 1. If canonicalPreview has page-indexed pages, find activePageNo:
    const previewPages = canonicalPreview?.artifact?.pages;
    if (Array.isArray(previewPages) && previewPages.length > 0) {
        const match = previewPages.find(
            (p) => Number(p.page_no) === Number(activePageNo),
        );
        if (match?.canonical_url) {
            return match.canonical_url;
        }
    }

    // 2. If canonicalPreview has legacy single canonical_url and activePageNo === 1:
    if (
        Number(activePageNo) === 1 &&
        canonicalPreview?.artifact?.canonical_url
    ) {
        return canonicalPreview.artifact.canonical_url;
    }

    // 3. Fallback to rasterUrl for the active page
    return rasterUrl ?? null;
}

export function syncBubblePanelOnPageChange({
    activePageNo,
    previousPageNo,
    sections,
    activeDocumentFieldId = null,
    documentFields = [],
}: {
    activePageNo: number;
    previousPageNo: number;
    sections: Array<{ id: string; page_no?: number }>;
    activeDocumentFieldId?: string | null;
    documentFields?: Array<{ id: string; page_no?: number }>;
}) {
    if (activePageNo === previousPageNo) {
        return null;
    }

    const pageFirstIdx = sections.findIndex(
        (s) => Number(s.page_no || 1) === Number(activePageNo),
    );
    const nextActiveSectionIndex = pageFirstIdx;
    const nextSelectedSectionIndices = pageFirstIdx >= 0 ? [pageFirstIdx] : [];

    let nextActiveDocumentFieldId = activeDocumentFieldId;
    if (activeDocumentFieldId) {
        const currentField = documentFields.find(
            (f) => f.id === activeDocumentFieldId,
        );
        if (
            currentField &&
            Number(currentField.page_no || 1) !== Number(activePageNo)
        ) {
            nextActiveDocumentFieldId = null;
        }
    }

    return {
        activeSectionIndex: nextActiveSectionIndex,
        selectedSectionIndices: nextSelectedSectionIndices,
        activeDocumentFieldId: nextActiveDocumentFieldId,
    };
}

describe('OMR studio page-aware URL resolution and synchronization contracts', () => {
    it('resolves page-indexed canonical URLs for multi-page previews', () => {
        const canonicalPreview = {
            artifact: {
                canonical_url: '/drafts/5/assets/61',
                pages: [
                    { page_no: 1, canonical_url: '/drafts/5/assets/61', asset_id: 61 },
                    { page_no: 2, canonical_url: '/drafts/5/assets/63', asset_id: 63 },
                ],
            },
        };

        const page1Url = resolveActiveSheetImageUrl({
            activePageNo: 1,
            canonicalPreview,
            rasterUrl: '/drafts/5/assets/61',
        });
        const page2Url = resolveActiveSheetImageUrl({
            activePageNo: 2,
            canonicalPreview,
            rasterUrl: '/drafts/5/assets/63',
        });

        expect(page1Url).toBe('/drafts/5/assets/61');
        expect(page2Url).toBe('/drafts/5/assets/63');
    });

    it('falls back to active page rasterUrl when canonicalPreview has no page 2 asset', () => {
        const canonicalPreview = {
            artifact: {
                canonical_url: '/drafts/5/assets/61',
                pages: [
                    { page_no: 1, canonical_url: '/drafts/5/assets/61', asset_id: 61 },
                ],
            },
        };

        const page2Url = resolveActiveSheetImageUrl({
            activePageNo: 2,
            canonicalPreview,
            rasterUrl: '/drafts/5/assets/63',
        });

        expect(page2Url).toBe('/drafts/5/assets/63');
    });

    it('never leaks page 1 canonical preview onto page 2 when rasterUrl is missing', () => {
        const canonicalPreview = {
            artifact: {
                canonical_url: '/drafts/5/assets/61',
                pages: [
                    { page_no: 1, canonical_url: '/drafts/5/assets/61', asset_id: 61 },
                ],
            },
        };

        const page2Url = resolveActiveSheetImageUrl({
            activePageNo: 2,
            canonicalPreview,
            rasterUrl: null,
        });

        expect(page2Url).toBeNull();
    });

    it('returns null in HTML sheet designer mode', () => {
        const canonicalPreview = {
            artifact: {
                canonical_url: '/drafts/5/assets/61',
                pages: [
                    { page_no: 1, canonical_url: '/drafts/5/assets/61', asset_id: 61 },
                ],
            },
        };

        const url = resolveActiveSheetImageUrl({
            activePageNo: 1,
            canonicalPreview,
            rasterUrl: '/drafts/5/assets/61',
            isHtmlMode: true,
        });

        expect(url).toBeNull();
    });

    it('synchronizes activeSectionIndex to the first section of the new page and clears other selections', () => {
        const sections = [
            { id: 'q1_p1', page_no: 1 },
            { id: 'q2_p1', page_no: 1 },
            { id: 'q3_p2', page_no: 2 },
            { id: 'q4_p2', page_no: 2 },
        ];
        const documentFields = [
            { id: 'field_name_p1', page_no: 1 },
            { id: 'field_sign_p2', page_no: 2 },
        ];

        // Switch from Page 1 to Page 2
        const p2Sync = syncBubblePanelOnPageChange({
            activePageNo: 2,
            previousPageNo: 1,
            sections,
            activeDocumentFieldId: 'field_name_p1',
            documentFields,
        });

        expect(p2Sync).not.toBeNull();
        expect(p2Sync?.activeSectionIndex).toBe(2); // q3_p2 is index 2
        expect(p2Sync?.selectedSectionIndices).toEqual([2]);
        expect(p2Sync?.activeDocumentFieldId).toBeNull(); // field_name_p1 belonged to page 1

        // Switch from Page 2 to Page 3 (empty page)
        const p3Sync = syncBubblePanelOnPageChange({
            activePageNo: 3,
            previousPageNo: 2,
            sections,
            activeDocumentFieldId: 'field_sign_p2',
            documentFields,
        });

        expect(p3Sync).not.toBeNull();
        expect(p3Sync?.activeSectionIndex).toBe(-1); // no sections on page 3
        expect(p3Sync?.selectedSectionIndices).toEqual([]);
        expect(p3Sync?.activeDocumentFieldId).toBeNull();
    });

    it('guards against rapid page toggling race conditions with monotonically increasing request tokens', () => {
        let activeToken = 0;
        let finalRenderedUrl = '';
        let finalLoaded = false;

        function startLoad(url: string) {
            const thisToken = ++activeToken;
            finalLoaded = false;
            return {
                complete: () => {
                    if (thisToken === activeToken) {
                        finalRenderedUrl = url;
                        finalLoaded = true;
                    }
                },
            };
        }

        // Simulate rapid toggling: Page 1 -> Page 2 -> Page 1
        const req1 = startLoad('/assets/page1.png');
        const req2 = startLoad('/assets/page2.png');
        const req3 = startLoad('/assets/page1.png');

        // Late response arrives from req2 (Page 2)
        req2.complete();
        expect(finalLoaded).toBe(false);
        expect(finalRenderedUrl).toBe('');

        // req3 (latest Page 1) completes
        req3.complete();
        expect(finalLoaded).toBe(true);
        expect(finalRenderedUrl).toBe('/assets/page1.png');

        // Stale req1 completes after
        req1.complete();
        expect(finalLoaded).toBe(true);
        expect(finalRenderedUrl).toBe('/assets/page1.png');
    });
});
