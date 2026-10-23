import { describe, expect, it } from 'vitest';
import { createStudioHistory } from '../../resources/js/lib/studioHistory';

describe('Studio undo and redo history', () => {
    it('restores states and reports the corresponding action labels', () => {
        const history = createStudioHistory({ count: 0 });
        history.record({ count: 1 }, 'Add component');
        history.record({ count: 2 }, 'Move component');

        expect(history.undo()).toEqual({ count: 1 });
        expect(history.status()).toMatchObject({
            canUndo: true,
            canRedo: true,
            undoLabel: 'Add component',
            redoLabel: 'Move component',
        });

        expect(history.redo()).toEqual({ count: 2 });
        expect(history.status()).toMatchObject({
            canUndo: true,
            canRedo: false,
            undoLabel: 'Move component',
            redoLabel: null,
        });
    });

    it('returns null at empty boundaries without throwing or returning undefined states', () => {
        const history = createStudioHistory({ count: 0 });

        expect(() => history.undo()).not.toThrow(ReferenceError);
        expect(history.undo()).toBeNull();
        expect(() => history.redo()).not.toThrow(ReferenceError);
        expect(history.redo()).toBeNull();
        expect(history.status()).toEqual({
            canUndo: false,
            canRedo: false,
            undoLabel: null,
            redoLabel: null,
        });

        history.record({ count: 1 }, 'Update component');
        const undoneState = history.undo();
        expect(undoneState).not.toBeUndefined();
        expect(undoneState).toEqual({ count: 0 });

        const redoneState = history.redo();
        expect(redoneState).not.toBeUndefined();
        expect(redoneState).toEqual({ count: 1 });
    });

    it('clears history and resets to a new state', () => {
        const history = createStudioHistory({ count: 0 });
        history.record({ count: 1 }, 'First change');
        history.undo();
        history.clear();

        expect(history.present()).toEqual({ count: 0 });
        expect(history.undo()).toBeNull();
        expect(history.redo()).toBeNull();
        expect(history.status()).toEqual({
            canUndo: false,
            canRedo: false,
            undoLabel: null,
            redoLabel: null,
        });

        history.record({ count: 2 }, 'Before reset');
        history.reset({ count: 5 });

        expect(history.present()).toEqual({ count: 5 });
        expect(history.undo()).toBeNull();
        expect(history.redo()).toBeNull();
        expect(history.status()).toEqual({
            canUndo: false,
            canRedo: false,
            undoLabel: null,
            redoLabel: null,
        });
    });
});
