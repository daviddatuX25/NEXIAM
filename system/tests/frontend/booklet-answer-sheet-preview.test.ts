// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AnswerSheetKeyPreview from '../../resources/js/Components/Setup/AnswerSheetKeyPreview.svelte';
import Booklets from '../../resources/js/Pages/Setup/Booklets.svelte';
import { buildBookletAnswerPreviewModel } from '../../resources/js/lib/bookletAnswerPreviewModel.js';

const mockRouterPost = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};
    return {
        ...actual,
        router: {
            ...actualRouter,
            post: (...args: unknown[]) => mockRouterPost(...args),
        },
    };
});

const rasterPage = {
    page_no: 1,
    width_px: 1000,
    height_px: 1400,
    page_count: 2,
    raster_url: '/private/kit/page-1/raster',
    raster_sha256: 'page-one-digest',
};
const physicalBubble = (uid: string, pageNo: number, x = 112) => ({
    physical_item_uid: uid,
    page_no: pageNo,
    question_no: 1,
    global_position: pageNo,
    options: {
        'A-B': { cx: x, cy: 205, r: 12 },
        'A-C': { cx: x + 30, cy: 205, r: 12 },
    },
});
const props = (overrides: Record<string, unknown> = {}) => ({
    page: { ...rasterPage, bubbles: [physicalBubble('physical-page-one', 1)] },
    bubbles: [physicalBubble('physical-page-one', 1)],
    answers: [{ physical_item_uid: 'physical-page-one', correct_option_code: 'A-B' }],
    status: 'ready',
    pageCount: 2,
    activePageNo: 1,
    scopeLabel: 'Saved Kit composition',
    readOnly: true,
    ...overrides,
});

afterEach(() => {
    cleanup();
    mockRouterPost.mockReset();
});

describe('Kit physical answer-key preview model', () => {
    it('shades only the exact opaque physical UID and literal composite token across pages', () => {
        const result = buildBookletAnswerPreviewModel({
            bubbles: [physicalBubble('uid-one', 1), physicalBubble('uid-two', 2, 212)],
            answers: [
                { physical_item_uid: 'uid-two', correct_option_code: 'A-C' },
                { physical_item_uid: 'other-kit-uid', correct_option_code: 'A-B' },
            ],
        });

        expect(result.overlays).toEqual([{
            physical_item_uid: 'uid-two',
            option: 'A-C',
            geometry: { cx: 242, cy: 205, r: 12 },
        }]);
        expect(result.missingPositions.map((item) => item.physical_item_uid)).toEqual(['uid-one']);
    });

    it('never defaults a missing, invalid, or duplicate key to the first option', () => {
        const bubbles = [physicalBubble('uid-one', 1)];
        for (const answers of [
            [],
            [{ physical_item_uid: 'uid-one', correct_option_code: 'Z' }],
            [
                { physical_item_uid: 'uid-one', correct_option_code: 'A-B' },
                { physical_item_uid: 'uid-one', correct_option_code: 'A-C' },
            ],
        ]) {
            const result = buildBookletAnswerPreviewModel({ bubbles, answers });
            expect(result.overlays).toEqual([]);
            expect(result.missingPositions).toHaveLength(1);
        }
    });
});

describe('AnswerSheetKeyPreview', () => {
    it('shades the saved Kit key only after the exact raster is loaded', async () => {
        const view = render(AnswerSheetKeyPreview, { props: props() });
        const image = view.container.querySelector('svg[role="img"] image') as Element;
        expect(view.container.querySelectorAll('circle[fill-opacity="0.78"]')).toHaveLength(0);
        await fireEvent.load(image);
        expect(view.container.querySelectorAll('circle[fill-opacity="0.78"]')).toHaveLength(1);
        expect(view.container.querySelector('circle[fill-opacity="0.78"]')?.getAttribute('cx')).toBe('112');
    });

    it('shows an unfilled structural page with no answer marks when composition is incomplete', async () => {
        const view = render(AnswerSheetKeyPreview, { props: props({
            structuralOnly: true,
            answers: [],
        }) });
        await fireEvent.load(view.container.querySelector('svg[role="img"] image') as Element);
        expect(view.getByText(/Unfilled locations: 1/)).toBeTruthy();
        expect(view.container.querySelectorAll('circle[fill-opacity="0.78"]')).toHaveLength(0);
        expect(view.getByText(/No answer-key marks are shown/)).toBeTruthy();
    });

    it('provides page navigation only within the supplied page bounds', async () => {
        const onPageChange = vi.fn();
        const view = render(AnswerSheetKeyPreview, { props: props({ pageCount: 2, onPageChange }) });
        await fireEvent.click(view.getByRole('button', { name: 'Next page' }));
        expect(onPageChange).toHaveBeenCalledWith(2);
        expect((view.getByRole('button', { name: 'Next page' }) as HTMLButtonElement).disabled).toBe(true);
    });
});
describe('Booklet intrinsic key workspace', () => {
    it('keeps Set key entry and intrinsic content preview in Booklets without a paired Answer Sheet surface', async () => {
        mockRouterPost.mockClear();
        const sectionUid = 'booklet-section-verbal';
        const item = {
            id: 501,
            booklet_item_uid: 'booklet-item-verbal-1',
            booklet_section_uid: sectionUid,
            local_number: '1',
            option_tokens: ['A-B', 'A-C'],
            key_required: true,
        };
        const workspace = {
            version_id: 6,
            version: 9,
            mode: 'answer_keys_only',
            lifecycle_status: 'official',
            allow_multiple_sets: true,
            required_set_codes: ['A', 'B', 'C', 'D'],
            lock_version: 1,
            is_finalized: false,
            can_discard: false,
            sections: [{ id: 81, uid: sectionUid, name: 'Verbal', required_count: 1, completed_count: 1 }],
            section_items: [{ id: 81, uid: sectionUid, name: 'Verbal', required_count: 1, items: [item] }],
            items: [item],
            sets: ['A', 'B', 'C', 'D'].map((setCode, index) => ({
                id: 61 + index,
                set_code: setCode,
                is_complete: false,
                items: [{
                    id: 71 + index,
                    booklet_item_id: 501,
                    booklet_item_uid: item.booklet_item_uid,
                    booklet_section_uid: sectionUid,
                    global_position: 1,
                    area_position: 1,
                    correct_option_code: setCode === 'A' ? 'A-B' : '',
                }],
            })),
        };
        const view = render(Booklets, {
            props: {
                enable_multi_set_booklets: true,
                selected_booklet_id: 6,
                booklets: [{ id: 6, name: 'Intrinsic Booklet', workspace }],
            },
        });

        expect(view.getByRole('region', { name: 'Intrinsic Booklet preview' })).toBeTruthy();
        expect(view.queryByRole('region', { name: 'Answer Sheet key preview' })).toBeNull();
        await fireEvent.click(view.getByRole('tab', { name: 'Set B' }));
        await fireEvent.click(view.getByRole('button', { name: 'Item 1: A-C' }));
        await fireEvent.click(view.getByRole('button', { name: 'Save Set B' }));

        expect(mockRouterPost).toHaveBeenCalledWith(
            '/setup/exam-materials/booklets/6/sets/B/keys',
            expect.objectContaining({
                complete: false,
                items: [expect.objectContaining({
                    booklet_item_uid: 'booklet-item-verbal-1',
                    booklet_section_uid: sectionUid,
                    global_position: 1,
                    selected_token: 'A-C',
                })],
            }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
