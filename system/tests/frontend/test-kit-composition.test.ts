// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrShadingGuideViewer from '../../resources/js/Components/Omr/Diagnostics/OmrShadingGuideViewer.svelte';
import KitCompositionEditor from '../../resources/js/Components/Setup/KitCompositionEditor.svelte';
import KitPairedPreview from '../../resources/js/Components/Setup/KitPairedPreview.svelte';
import { buildBookletAnswerPreviewModel } from '../../resources/js/lib/bookletAnswerPreviewModel.js';


const composition = {
    preparation_id: 22,
    revision: 9,
    component_vector: { sheet: 'sheet-revision', booklet: 'booklet-revision', profile: 'profile-revision' },
    matched: 0,
    expected: 1,
    save_url: '/kits/7/preparation/composition',
    groups: [{
        physical_section_uid: 'section-vocabulary',
        label: 'Vocabulary',
        matched: 0,
        expected: 1,
        physical_locations: [{ physical_item_uid: 'physical-location-uuid', sheet_item_id: 81, page_no: 2, native_label: 'VOCABULARY_Q_1', label: 'Question 1', option_tokens: ['A-B', 'A-C'] }],
        booklet_sections: [{ booklet_section_uid: 'booklet-section-uuid', label: 'Verbal', items: [{ booklet_item_id: 34, booklet_item_uid: 'booklet-item-uuid', label: 'Item 1', option_tokens: ['A-B', 'A-C'] }] }],
        formal_subtests: [{ formal_subtest_id: 12, label: 'Verbal reasoning', items: [{ formal_item_id: 45, label: 'Item 1', option_tokens: ['A-B', 'A-C'] }] }],
    }],
    rows: [],
};

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('Kit exact composition controls', () => {
    it('posts exact mapping with the draft revision and preparation identity', async () => {
        const fetchMock = vi.fn(async (_url: string, _options?: RequestInit) => new Response(JSON.stringify({
            ...composition,
            revision: 10,
        }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
        const onSave = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const view = render(KitCompositionEditor, { props: { composition, preparationId: 22, onSave } });
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        const dialog = view.getByRole('dialog', { name: 'Match Vocabulary' });
        const selects = within(dialog).getAllByRole('combobox');
        await fireEvent.change(selects[0], { target: { value: 'booklet-section-uuid' } });
        await fireEvent.change(selects[1], { target: { value: '12' } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Match in order' }));
        expect((within(dialog).getAllByRole('combobox')[2] as HTMLSelectElement).value).toBe('34');
        expect((within(dialog).getAllByRole('combobox')[3] as HTMLSelectElement).value).toBe('45');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        expect(fetchMock.mock.calls[0][0]).toBe('/kits/7/preparation/composition');
        const request = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(request).toMatchObject({
            preparation_id: 22,
            expected_revision: 9,
            component_vector: composition.component_vector,
            rows: [{
                physical_item_uid: 'physical-location-uuid',
                booklet_item_id: 34,
                formal_subtest_id: 12,
                formal_item_id: 45,
                option_tokens: ['A-B', 'A-C'],
                disposition: 'physical',
            }],
        });
        expect(request.idempotency_key).toEqual(expect.any(String));
        await waitFor(() => expect(onSave).toHaveBeenCalledWith(expect.objectContaining({
            preparation_id: 22,
            revision: 10,
        })));
    });
    it('does not render a fabricated zero-of-zero count for non-applicable mapping', () => {
        const view = render(KitCompositionEditor, {
            props: { composition: { ...composition, matched: null, expected: null } },
        });

        expect(view.getByText('Not applicable')).toBeTruthy();
    });

    it('keeps the reviewed edits and reports Laravel validation errors', async () => {
        const fetchMock = vi.fn(async () => new Response(JSON.stringify({
            message: 'The given data was invalid.',
            errors: { composition: ['The draft changed.'] },
        }), { status: 422, headers: { 'Content-Type': 'application/json' } }));
        vi.stubGlobal('fetch', fetchMock);
        const view = render(KitCompositionEditor, { props: { composition, preparationId: 22 } });
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        const dialog = view.getByRole('dialog', { name: 'Match Vocabulary' });
        const selects = within(dialog).getAllByRole('combobox');
        await fireEvent.change(selects[0], { target: { value: 'booklet-section-uuid' } });
        await fireEvent.change(selects[1], { target: { value: '12' } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Match in order' }));
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

        expect((await view.findByRole('alert')).textContent).toContain('The draft changed.');
        expect(view.getByRole('dialog')).toBeTruthy();
        expect((within(dialog).getAllByRole('combobox')[2] as HTMLSelectElement).value).toBe('34');
    });

    it('ignores a successful save acknowledgement after its preparation context changes', async () => {
        let resolveResponse: (response: Response) => void = () => {};
        const fetchMock = vi.fn(() => new Promise<Response>((resolve) => { resolveResponse = resolve; }));
        const onSave = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const view = render(KitCompositionEditor, { props: { composition, preparationId: 22, onSave } });
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        const dialog = view.getByRole('dialog', { name: 'Match Vocabulary' });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        await view.rerender({
            composition: { ...composition, preparation_id: 23, revision: 10 },
            preparationId: 23,
            onSave,
        });
        resolveResponse(new Response(JSON.stringify({ ...composition, revision: 10 }), { status: 200 }));
        await Promise.resolve();

        expect(onSave).not.toHaveBeenCalled();
        expect(view.getByRole('dialog')).toBeTruthy();
    });

    it('does not map by equal counts or similar labels and keeps mismatch feedback visible', async () => {
        const view = render(KitCompositionEditor, { props: {
            composition: {
                ...composition,
                rows: [{ physical_item_uid: 'physical-location-uuid', booklet_item_id: null, formal_subtest_id: null, formal_item_id: null, option_tokens: ['A-B', 'A-C'], issues: 'Choose an explicit Booklet and Test Setup identity.' }],
            },
        } });
        expect(view.getByText('0 of 1 matched')).toBeTruthy();
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        expect(view.getByRole('alert').textContent).toContain('explicit Booklet and Test Setup identity');
        expect(within(view.getByRole('dialog')).getAllByRole('combobox').every((select) => (select as HTMLSelectElement).value === '')).toBe(true);
    });

    it('closes through the shared dialog Escape path and can reopen the same section', async () => {
        const view = render(KitCompositionEditor, { props: { composition } });
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        expect(view.getByRole('dialog')).toBeTruthy();
        await fireEvent.keyDown(document, { key: 'Escape' });
        expect(view.queryByRole('dialog')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        expect(view.getByRole('dialog', { name: 'Match Vocabulary' })).toBeTruthy();
    });

    it('requires an explicit nonphysical-practice disposition for unscored items', async () => {
        const fetchMock = vi.fn(async (_url: string, _options?: RequestInit) => new Response(JSON.stringify({
            ...composition,
            revision: 10,
        }), { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);
        const view = render(KitCompositionEditor, { props: { composition: {
            ...composition,
            nonphysical_practice_items: [{
                formal_subtest_id: 12,
                subtest_label: 'Verbal reasoning',
                formal_item_id: 99,
                label: 'Practice item',
                option_tokens: ['A-B', 'A-C'],
                is_scored: false,
            }],
        } } });
        await fireEvent.click(view.getByRole('button', { name: 'Match' }));
        const dialog = view.getByRole('dialog');
        await fireEvent.change(within(dialog).getByLabelText('Choose an unscored practice item'), { target: { value: '99' } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Add practice item' }));
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        expect(JSON.parse(fetchMock.mock.calls[0][1].body).rows).toContainEqual({
            physical_item_uid: null,
            booklet_item_id: null,
            formal_subtest_id: 12,
            formal_item_id: 99,
            option_tokens: [],
            disposition: 'nonphysical_practice',
        });
    });


    it('shows a row-level option mismatch instead of treating equal counts as compatible', async () => {
        const view = render(KitCompositionEditor, { props: {
            composition: {
                ...composition,
                groups: [{
                    ...composition.groups[0],
                    matched: 1,
                    booklet_sections: [{ booklet_section_uid: 'booklet-section-uuid', label: 'Verbal', items: [{ booklet_item_id: 34, booklet_item_uid: 'booklet-item-uuid', label: 'Item 1', option_tokens: ['A-B', 'A-C'] }] }],
                    formal_subtests: [{ formal_subtest_id: 12, label: 'Verbal reasoning', items: [{ formal_item_id: 45, label: 'Item 1', option_tokens: ['A-B'] }] }],
                }],
                rows: [{ physical_item_uid: 'physical-location-uuid', booklet_item_id: 34, formal_subtest_id: 12, formal_item_id: 45, option_tokens: ['A-B', 'A-C'], disposition: 'physical' }],
            },
        } });
        await fireEvent.click(view.getByRole('button', { name: 'Review' }));
        expect(view.getByRole('alert').textContent).toContain('Option choices differ');
        expect(view.getByText('Test Setup choices').parentElement?.textContent).toContain('A-B');
    });
    it('shades only a matching opaque physical UID and literal answer token', () => {
        const correct = buildBookletAnswerPreviewModel({
            bubbles: [
                { physical_item_uid: 'physical-1', page_no: 1, options: { 'A-B': { cx: 20, cy: 30, r: 4 } } },
                { physical_item_uid: 'physical-2', page_no: 2, options: { 'A-B': { cx: 40, cy: 50, r: 4 } } },
            ],
            answers: [{ physical_item_uid: 'physical-2', correct_option_code: 'A-B' }],
        });
        expect(correct.overlays).toEqual([{ physical_item_uid: 'physical-2', option: 'A-B', geometry: { cx: 40, cy: 50, r: 4 } }]);
        expect(correct.missingPositions).toEqual([{ physical_item_uid: 'physical-1', page_no: 1, question_no: undefined, global_position: undefined }]);

        const invalidKey = buildBookletAnswerPreviewModel({
            bubbles: [{ physical_item_uid: 'physical-2', page_no: 2, options: { 'A-B': { cx: 40, cy: 50, r: 4 } } }],
            answers: [{ physical_item_uid: 'physical-2', correct_option_code: 'A' }],
        });
        expect(invalidKey.overlays).toEqual([]);
        expect(invalidKey.missingPositions).toHaveLength(1);
    });

    it('does not display a late response after the preview context changes', async () => {
        let resolveFirst: (value: Response) => void = () => {};
        const first = new Promise<Response>((resolve) => { resolveFirst = resolve; });
        vi.stubGlobal('fetch', vi.fn(() => first));
        const view = render(KitPairedPreview, { props: { previewUrl: '/kits/7/preview?revision=4', contextRevision: 9 } });
        await view.rerender({ previewUrl: '/kits/7/preview?revision=5', contextRevision: 10 });
        resolveFirst(new Response(JSON.stringify({ state: 'ready', pages: [], answers: [], context: { preparation_revision: 9 } }), { status: 200 }));
        await Promise.resolve();
        expect(view.queryByText(/stale_preview_context/i)).toBeNull();
        expect(view.queryByText('Saved Kit composition · Answer Sheet')).toBeNull();
        vi.unstubAllGlobals();
    });

    it('refuses a paired preview response from a replaced preparation context', async () => {
        const snapshot = {
            preparation_id: 22,
            preparation_revision: 9,
            composition_revision: 4,
            component_vector_sha256: 'expected-vector-digest',
        };
        vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({
            state: 'ready',
            page_count: 1,
            pages: [{ page_no: 1, width_px: 100, height_px: 100, raster_url: '/old-raster', raster_sha256: 'old-hash', bubbles: [] }],
            answers: [],
            context: { ...snapshot, preparation_id: 21 },
        }), { status: 200 })));
        const view = render(KitPairedPreview, { props: {
            previewUrl: '/kits/7/preparation/composition/preview?preparation_id=22',
            contextSnapshot: snapshot,
        } });
        await waitFor(() => expect(view.getByRole('status').textContent).toContain('No usable raster page'));
        expect(view.container.querySelector('svg image')).toBeNull();
        vi.unstubAllGlobals();
    });

    it('does not re-fetch or flash loading when contextSnapshot receives a new object reference with identical values', async () => {
        const snapshot = {
            test_kit_version_id: 2,
            composition_revision: 3,
            composition_sha256: 'same-composition-sha',
            manifest_sha256: 'same-manifest-sha',
        };
        const fetchSpy = vi.fn(async () => new Response(JSON.stringify({
            state: 'ready',
            page_count: 1,
            pages: [{ page_no: 1, width_px: 2480, height_px: 3508, raster_url: '/raster-1.png', raster_sha256: 'sha-1', bubbles: [] }],
            answers: [],
            context: { ...snapshot },
        }), { status: 200 }));
        vi.stubGlobal('fetch', fetchSpy);
        const view = render(KitPairedPreview, {
            props: {
                previewUrl: '/kits/1/2/composition/preview',
                contextRevision: 3,
                contextSnapshot: { ...snapshot },
            },
        });
        await waitFor(() => expect(view.container.querySelector('svg image')).not.toBeNull());
        expect(fetchSpy).toHaveBeenCalledTimes(1);

        await view.rerender({
            previewUrl: '/kits/1/2/composition/preview',
            contextRevision: 3,
            contextSnapshot: { ...snapshot },
        });
        expect(fetchSpy).toHaveBeenCalledTimes(1);
        expect(view.queryByText(/Loading selected Answer Sheet/i)).toBeNull();
        expect(view.container.querySelector('svg image')).not.toBeNull();
        vi.unstubAllGlobals();
    });

    it('highlights both the question set and target bubbles when an intentional mark is clicked in OmrShadingGuideViewer', async () => {
        const view = render(OmrShadingGuideViewer, {
            props: {
                instanceCount: 1,
                selectedInstance: 1,
                pages: [{
                    page_no: 1,
                    width_px: 2480,
                    height_px: 3508,
                    image_url: '/mock-page-1.png',
                    instances: [{
                        instance_no: 1,
                        instructions: [{
                            page_no: 1,
                            native_label: 'VOCABULARY_Q_1',
                            intent: 'double',
                            target_tokens: ['A-B', 'A-C'],
                            key_token: 'A-B',
                        }],
                        bubbles: [
                            { native_label: 'VOCABULARY_Q_1', token: 'A-B', x: 212, y: 1269, w: 32, h: 32, style: 'double' },
                            { native_label: 'VOCABULARY_Q_1', token: 'A-C', x: 318, y: 1269, w: 32, h: 32, style: 'double' },
                            { native_label: 'VOCABULARY_Q_1', token: 'A-D', x: 424, y: 1269, w: 32, h: 32, style: 'outline' },
                        ],
                    }],
                }],
            },
        });

        const markButton = view.container.querySelector('[data-mark-label="VOCABULARY_Q_1"]') as HTMLButtonElement;
        expect(markButton).not.toBeNull();
        await fireEvent.click(markButton);

        expect(view.container.querySelector('[data-highlighted-question-set="VOCABULARY"]')).not.toBeNull();
        expect(view.container.querySelector('[data-highlighted-question-row="VOCABULARY_Q_1"]')).not.toBeNull();
        expect(view.container.querySelector('[data-highlighted-target-bubble="VOCABULARY_Q_1:A-B"]')).not.toBeNull();
        expect(view.container.querySelector('[data-highlighted-target-bubble="VOCABULARY_Q_1:A-C"]')).not.toBeNull();
        expect(view.container.querySelector('[data-highlighted-target-bubble="VOCABULARY_Q_1:A-D"]')).toBeNull();
    });
});
