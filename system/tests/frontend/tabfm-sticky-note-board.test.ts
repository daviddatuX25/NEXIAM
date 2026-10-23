// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TabFmStickyNoteBoard from '../../resources/js/Components/admission-support/TabFmStickyNoteBoard.svelte';

const emptyNotes = { items: [], pagination: null, counts: { total: 0, drafts: 0, shared: 0 } };

afterEach(cleanup);

describe('TabFmStickyNoteBoard', () => {
    it('offers one local Add note action for a successful empty board without showing search or an empty note pane', async () => {
        const onNew = vi.fn();
        const view = render(TabFmStickyNoteBoard, { props: { notes: emptyNotes, canEdit: true, onNew } });

        expect(screen.getAllByRole('button', { name: 'Add note' })).toHaveLength(1);
        expect(screen.queryByLabelText('Search notes')).toBeNull();
        expect(screen.queryByText(/No notes yet/)).toBeNull();
        expect(screen.queryByText(/Choose a note/)).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: 'Add note' }));
        expect(onNew).toHaveBeenCalledTimes(1);
        await view.rerender({
            props: { notes: emptyNotes, canEdit: true, onNew, selectedNote: { note_id: null, version: 0, content: '' } },
        });
        expect(screen.queryByRole('button', { name: 'Add note' })).toBeNull();
    });

    it('keeps loading, denied, unavailable, and search-no-match states distinct', () => {
        const loading = render(TabFmStickyNoteBoard, { props: { notes: emptyNotes, loading: true } });
        expect(screen.getByRole('status').textContent).toContain('Loading notes');
        expect(screen.queryByRole('button', { name: 'Add note' })).toBeNull();
        loading.unmount();

        const denied = render(TabFmStickyNoteBoard, { props: { notes: emptyNotes, denied: true, canEdit: true } });
        expect(screen.getByText(/not available for this programme and academic year/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Add note' })).toBeNull();
        denied.unmount();

        const error = render(TabFmStickyNoteBoard, { props: { notes: emptyNotes, error: 'Notes could not be loaded.' } });
        expect(screen.getByRole('alert').textContent).toBe('Notes could not be loaded.');
        error.unmount();

        render(TabFmStickyNoteBoard, {
            props: { notes: { ...emptyNotes, counts: { total: 2, drafts: 2, shared: 0 } }, search: 'not found' },
        });
        expect(screen.getByRole('status').textContent).toContain('No notes match this search');
    });

});
