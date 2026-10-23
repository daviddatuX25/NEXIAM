/**
 * @vitest-environment jsdom
 */

import { describe, expect, it, vi } from 'vitest';
import {
  // Geometry
  alignRects,
  calculateSnapGuides,
  clamp,
  clampToBounds,
  distributeRects,
  getAlignmentDelta,
  getBoundingBox,
  lockAxis,
  normalizeRect,
  pointInRect,
  rectContains,
  rectIntersects,
  // History
  createStudioHistory,
  StudioHistory,
  // Selection
  createStudioSelection,
  StudioSelection,
  // Layers
  bringToFront,
  createStudioLayers,
  filterUnlocked,
  findLayerIndex,
  getZIndexMap,
  isLocked,
  moveDown,
  moveItemsToIndex,
  moveUp,
  reorder,
  sendToBack,
  setLocked,
  StudioLayers,
  toggleLocked,
  // Keymap
  createStudioKeymap,
  DEFAULT_KEY_BINDINGS,
  handleKeyDown,
  isInputFocused,
  isMod,
  matchKeyBinding,
  StudioKeymap,
  // Viewport
  calculateCursorZoom,
  clampZoom,
  calculateWheelZoomFactor,
  fitViewport,
  DEFAULT_MIN_ZOOM,
  DEFAULT_MAX_ZOOM,
} from '../../resources/js/lib/studio/index.js';
import type {
  IdentifiedRect,
  LayerItem,
  Rect,
  StudioCommand,
} from '../../resources/js/lib/studio/index.js';

describe('Studio Geometry Module', () => {
  describe('normalizeRect', () => {
    it('preserves standard positive rectangles', () => {
      const rect: Rect = { x: 10, y: 20, width: 30, height: 40 };
      expect(normalizeRect(rect)).toEqual({ x: 10, y: 20, width: 30, height: 40 });
    });

    it('normalizes rectangles with negative width or height', () => {
      const negativeWidth: Rect = { x: 50, y: 20, width: -30, height: 40 };
      expect(normalizeRect(negativeWidth)).toEqual({ x: 20, y: 20, width: 30, height: 40 });

      const negativeHeight: Rect = { x: 10, y: 60, width: 30, height: -40 };
      expect(normalizeRect(negativeHeight)).toEqual({ x: 10, y: 20, width: 30, height: 40 });

      const bothNegative: Rect = { x: 50, y: 60, width: -30, height: -40 };
      expect(normalizeRect(bothNegative)).toEqual({ x: 20, y: 20, width: 30, height: 40 });
    });
  });

  describe('rectIntersects', () => {
    it('detects intersecting rectangles', () => {
      const a: Rect = { x: 0, y: 0, width: 50, height: 50 };
      const b: Rect = { x: 25, y: 25, width: 50, height: 50 };
      expect(rectIntersects(a, b)).toBe(true);
      expect(rectIntersects(b, a)).toBe(true);
    });

    it('returns false for disjoint rectangles', () => {
      const a: Rect = { x: 0, y: 0, width: 50, height: 50 };
      const b: Rect = { x: 60, y: 60, width: 50, height: 50 };
      expect(rectIntersects(a, b)).toBe(false);
    });

    it('returns false for edge-touching non-overlapping rectangles', () => {
      const a: Rect = { x: 0, y: 0, width: 50, height: 50 };
      const b: Rect = { x: 50, y: 0, width: 50, height: 50 };
      expect(rectIntersects(a, b)).toBe(false);
    });

    it('handles negative dimension drag rectangles', () => {
      const marquee: Rect = { x: 80, y: 80, width: -60, height: -60 }; // normalized: { x: 20, y: 20, width: 60, height: 60 }
      const item: Rect = { x: 40, y: 40, width: 20, height: 20 };
      expect(rectIntersects(marquee, item)).toBe(true);
    });
  });

  describe('rectContains', () => {
    it('returns true when inner is strictly inside container', () => {
      const container: Rect = { x: 0, y: 0, width: 100, height: 100 };
      const inner: Rect = { x: 10, y: 10, width: 50, height: 50 };
      expect(rectContains(container, inner)).toBe(true);
    });

    it('returns true for identical rectangles', () => {
      const a: Rect = { x: 10, y: 10, width: 50, height: 50 };
      expect(rectContains(a, a)).toBe(true);
    });

    it('returns false when inner overlaps partially outside container', () => {
      const container: Rect = { x: 0, y: 0, width: 100, height: 100 };
      const inner: Rect = { x: 80, y: 80, width: 50, height: 50 };
      expect(rectContains(container, inner)).toBe(false);
    });
  });

  describe('pointInRect', () => {
    it('detects points inside and on boundary', () => {
      const rect: Rect = { x: 10, y: 10, width: 80, height: 80 };
      expect(pointInRect({ x: 50, y: 50 }, rect)).toBe(true);
      expect(pointInRect({ x: 10, y: 10 }, rect)).toBe(true);
      expect(pointInRect({ x: 90, y: 90 }, rect)).toBe(true);
      expect(pointInRect({ x: 5, y: 50 }, rect)).toBe(false);
    });
  });

  describe('clamp and clampToBounds', () => {
    it('clamps scalar values properly', () => {
      expect(clamp(5, 0, 10)).toBe(5);
      expect(clamp(-5, 0, 10)).toBe(0);
      expect(clamp(15, 0, 10)).toBe(10);
    });

    it('clamps rectangles inside printable bounds', () => {
      const bounds: Rect = { x: 12, y: 12, width: 186, height: 273 }; // A4 with 12mm margins
      const component: Rect = { x: 200, y: 300, width: 50, height: 30 };

      const clamped = clampToBounds(component, bounds);
      expect(clamped.x).toBe(148); // 12 + 186 - 50 = 148
      expect(clamped.y).toBe(255); // 12 + 273 - 30 = 255
      expect(clamped.width).toBe(50);
      expect(clamped.height).toBe(30);
    });

    it('constrains component width/height if larger than bounds', () => {
      const bounds: Rect = { x: 0, y: 0, width: 100, height: 100 };
      const oversized: Rect = { x: -10, y: -10, width: 150, height: 150 };

      const clamped = clampToBounds(oversized, bounds);
      expect(clamped).toEqual({ x: 0, y: 0, width: 100, height: 100 });
    });
  });

  describe('lockAxis', () => {
    it('locks to horizontal axis when horizontal delta is dominant', () => {
      expect(lockAxis(15, 2)).toEqual({ dx: 15, dy: 0 });
      expect(lockAxis(-20, 10)).toEqual({ dx: -20, dy: 0 });
    });

    it('locks to vertical axis when vertical delta is dominant', () => {
      expect(lockAxis(3, 25)).toEqual({ dx: 0, dy: 25 });
      expect(lockAxis(-5, -40)).toEqual({ dx: 0, dy: -40 });
    });

    it('defaults to horizontal on exact 45-degree diagonal', () => {
      expect(lockAxis(10, 10)).toEqual({ dx: 10, dy: 0 });
    });

    it('respects threshold when movement is minute', () => {
      expect(lockAxis(1, 1, 3)).toEqual({ dx: 0, dy: 0 });
      expect(lockAxis(4, 1, 3)).toEqual({ dx: 4, dy: 0 });
    });
  });

  describe('getBoundingBox', () => {
    it('returns null for empty array', () => {
      expect(getBoundingBox([])).toBeNull();
    });

    it('computes collective bounding box for multiple rectangles', () => {
      const rects: Rect[] = [
        { x: 10, y: 20, width: 30, height: 40 },
        { x: 100, y: 50, width: 40, height: 60 },
      ];
      expect(getBoundingBox(rects)).toEqual({
        x: 10,
        y: 20,
        width: 130, // 140 - 10
        height: 90, // 110 - 20
      });
    });
  });

  describe('alignment and distribution', () => {
    const items: IdentifiedRect[] = [
      { id: '1', x: 10, y: 10, width: 20, height: 20 },
      { id: '2', x: 62, y: 30, width: 30, height: 40 },
      { id: '3', x: 100, y: 80, width: 20, height: 20 },
    ];

    it('calculates alignment deltas correctly', () => {
      const ref: Rect = { x: 0, y: 0, width: 100, height: 100 };
      const target: Rect = { x: 20, y: 20, width: 40, height: 40 };

      expect(getAlignmentDelta(target, ref, 'left')).toEqual({ dx: -20, dy: 0 });
      expect(getAlignmentDelta(target, ref, 'center')).toEqual({ dx: 10, dy: 0 });
      expect(getAlignmentDelta(target, ref, 'right')).toEqual({ dx: 40, dy: 0 });
      expect(getAlignmentDelta(target, ref, 'top')).toEqual({ dx: 0, dy: -20 });
      expect(getAlignmentDelta(target, ref, 'middle')).toEqual({ dx: 0, dy: 10 });
      expect(getAlignmentDelta(target, ref, 'bottom')).toEqual({ dx: 0, dy: 40 });
    });

    it('aligns items to left bound without mutating inputs', () => {
      const aligned = alignRects(items, 'left');
      expect(aligned[0].x).toBe(10);
      expect(aligned[1].x).toBe(10);
      expect(aligned[2].x).toBe(10);
      expect(items[1].x).toBe(62); // Immutable check
    });

    it('distributes items horizontally with equal gaps', () => {
      const distributed = distributeRects(items, 'horizontal');
      expect(distributed[0].x).toBe(10);
      expect(distributed[1].x).toBe(50);
      expect(distributed[2].x).toBe(100);
    });

    it('distributes items vertically with equal gaps', () => {
      const distributed = distributeRects(items, 'vertical');
      expect(distributed[0].y).toBe(10);
      expect(distributed[1].y).toBe(35);
      expect(distributed[2].y).toBe(80);
    });
  });

  describe('calculateSnapGuides', () => {
    it('snaps dragged rect within tolerance and emits guide lines', () => {
      const other: Rect[] = [{ x: 100, y: 100, width: 50, height: 50 }];
      const dragged: Rect = { x: 99, y: 40, width: 50, height: 40 }; // 1mm off from x=100

      const result = calculateSnapGuides(dragged, other, 2);
      expect(result.snappedRect.x).toBe(100);
      expect(result.guides).toHaveLength(1);
      expect(result.guides[0]).toEqual({
        axis: 'x',
        position: 100,
        origin: 40,
        target: 150,
      });
    });

    it('does not snap when distance exceeds tolerance', () => {
      const other: Rect[] = [{ x: 100, y: 100, width: 50, height: 50 }];
      const dragged: Rect = { x: 90, y: 40, width: 50, height: 40 }; // 10mm off

      const result = calculateSnapGuides(dragged, other, 2);
      expect(result.snappedRect.x).toBe(90);
      expect(result.guides).toHaveLength(0);
    });
  });
});

describe('Studio History Module', () => {
  interface EditorState {
    count: number;
    title: string;
  }

  const initial: EditorState = { count: 0, title: 'Draft' };

  it('initializes with clean present state', () => {
    const history = createStudioHistory(initial);
    expect(history.present).toEqual(initial);
    expect(history.canUndo()).toBe(false);
    expect(history.canRedo()).toBe(false);
    expect(history.isDirty()).toBe(false);
  });

  it('records state and manages undo/redo stacks', () => {
    const history = createStudioHistory(initial);

    const recorded = history.record({ count: 1, title: 'Draft' }, 'Increment count');
    expect(recorded).toBe(true);
    expect(history.present.count).toBe(1);
    expect(history.canUndo()).toBe(true);
    expect(history.isDirty()).toBe(true);

    const undone = history.undo();
    expect(undone).toEqual(initial);
    expect(history.present.count).toBe(0);
    expect(history.canUndo()).toBe(false);
    expect(history.canRedo()).toBe(true);

    const redone = history.redo();
    expect(redone?.count).toBe(1);
    expect(history.present.count).toBe(1);
  });

  it('ignores recording identical state', () => {
    const history = createStudioHistory(initial);
    const recorded = history.record({ count: 0, title: 'Draft' }, 'No-op change');
    expect(recorded).toBe(false);
    expect(history.canUndo()).toBe(false);
  });

  it('coalesces rapid changes sharing coalesceKey within windowMs', () => {
    const history = createStudioHistory(initial, { coalesceWindowMs: 400 });

    // Frame 1: move from 0 to 10
    history.record({ count: 10, title: 'Draft' }, 'Drag component', { coalesceKey: 'drag-1' });
    // Frame 2: move from 10 to 20 (within window)
    history.record({ count: 20, title: 'Draft' }, 'Drag component', { coalesceKey: 'drag-1' });
    // Frame 3: move from 20 to 50 (within window)
    history.record({ count: 50, title: 'Draft' }, 'Drag component', { coalesceKey: 'drag-1' });

    expect(history.present.count).toBe(50);
    expect(history.status().pastCount).toBe(1);

    // Single undo step restores back to initial state 0 (not 20 or 10)
    const reverted = history.undo();
    expect(reverted?.count).toBe(0);
    expect(history.present.count).toBe(0);
    expect(history.canUndo()).toBe(false);
  });

  it('enforces maxDepth boundary', () => {
    const history = createStudioHistory(initial, { maxDepth: 3 });

    history.record({ count: 1, title: '1' }, 'Step 1');
    history.record({ count: 2, title: '2' }, 'Step 2');
    history.record({ count: 3, title: '3' }, 'Step 3');
    history.record({ count: 4, title: '4' }, 'Step 4');

    expect(history.status().pastCount).toBe(3);
    history.undo(); // count -> 3
    history.undo(); // count -> 2
    history.undo(); // count -> 1
    expect(history.canUndo()).toBe(false);
    expect(history.present.count).toBe(1);
  });

  it('supports gesture transaction commit lifecycle', () => {
    const history = createStudioHistory(initial);

    history.beginGesture('Resize component', 'resize-1');
    expect(history.isInGesture()).toBe(true);

    history.updateGesture({ count: 10, title: 'Resizing' });
    history.updateGesture({ count: 25, title: 'Resizing' });
    expect(history.present.count).toBe(25);
    expect(history.canUndo()).toBe(false); // No history entries during active gesture

    const committed = history.commitGesture();
    expect(committed).toBe(true);
    expect(history.isInGesture()).toBe(false);
    expect(history.canUndo()).toBe(true);

    history.undo();
    expect(history.present.count).toBe(0);
  });

  it('supports gesture transaction cancel restoration', () => {
    const history = createStudioHistory(initial);

    history.beginGesture('Drag component', 'drag-1');
    history.updateGesture({ count: 99, title: 'Draft' });
    expect(history.present.count).toBe(99);

    const restored = history.cancelGesture();
    expect(restored.count).toBe(0);
    expect(history.present.count).toBe(0);
    expect(history.canUndo()).toBe(false);
  });

  it('handles reset and markClean', () => {
    const history = createStudioHistory(initial);
    history.record({ count: 5, title: 'Draft' }, 'Edit');
    expect(history.isDirty()).toBe(true);

    history.markClean();
    expect(history.isDirty()).toBe(false);

    history.reset({ count: 100, title: 'Clean Slate' });
    expect(history.present.count).toBe(100);
    expect(history.canUndo()).toBe(false);
    expect(history.isDirty()).toBe(false);
  });
});

describe('Studio Selection Module', () => {
  it('manages single select and primary tracking', () => {
    const selection = createStudioSelection();
    expect(selection.isEmpty()).toBe(true);

    selection.select('comp-1');
    expect(selection.isSelected('comp-1')).toBe(true);
    expect(selection.getPrimaryId()).toBe('comp-1');
    expect(selection.count).toBe(1);
    expect(selection.hasSingle()).toBe(true);

    selection.select('comp-2');
    expect(selection.isSelected('comp-1')).toBe(false);
    expect(selection.isSelected('comp-2')).toBe(true);
    expect(selection.getPrimaryId()).toBe('comp-2');
  });

  it('toggles selection and promotes last remaining item as primary', () => {
    const selection = createStudioSelection();

    selection.toggle('comp-1');
    expect(selection.isSelected('comp-1')).toBe(true);
    expect(selection.getPrimaryId()).toBe('comp-1');

    selection.toggle('comp-2');
    expect(selection.getSelection()).toEqual(['comp-1', 'comp-2']);
    expect(selection.getPrimaryId()).toBe('comp-2');

    // Deselecting primary promotes comp-1 to primary
    selection.toggle('comp-2');
    expect(selection.getSelection()).toEqual(['comp-1']);
    expect(selection.getPrimaryId()).toBe('comp-1');

    // Deselecting comp-1 clears primary
    selection.toggle('comp-1');
    expect(selection.isEmpty()).toBe(true);
    expect(selection.getPrimaryId()).toBeNull();
  });

  it('supports add, remove, and selectAll', () => {
    const selection = createStudioSelection();

    selection.add(['a', 'b', 'c']);
    expect(selection.count).toBe(3);
    expect(selection.hasMultiple()).toBe(true);
    expect(selection.getPrimaryId()).toBe('c');

    selection.remove('c');
    expect(selection.count).toBe(2);
    expect(selection.getPrimaryId()).toBe('b');

    selection.selectAll(['x', 'y', 'z']);
    expect(selection.count).toBe(3);
    expect(selection.getSelection()).toEqual(['x', 'y', 'z']);

    selection.clear();
    expect(selection.isEmpty()).toBe(true);
    expect(selection.getPrimaryId()).toBeNull();
  });

  it('queries and executes marquee selection', () => {
    const items: IdentifiedRect[] = [
      { id: 'item-1', x: 10, y: 10, width: 20, height: 20 },
      { id: 'item-2', x: 50, y: 50, width: 20, height: 20 },
      { id: 'item-3', x: 100, y: 100, width: 20, height: 20 },
    ];

    const selection = createStudioSelection();
    const marquee: Rect = { x: 0, y: 0, width: 80, height: 80 };

    const matches = selection.selectMarquee(items, marquee);
    expect(matches).toEqual(['item-1', 'item-2']);
    expect(selection.getSelection()).toEqual(['item-1', 'item-2']);

    // Additive marquee
    const marquee2: Rect = { x: 90, y: 90, width: 40, height: 40 };
    selection.selectMarquee(items, marquee2, true);
    expect(selection.getSelection()).toEqual(['item-1', 'item-2', 'item-3']);
  });

  it('serializes and clones state accurately', () => {
    const selection = createStudioSelection(['a', 'b'], 'b');
    const serialized = selection.toState();
    expect(serialized).toEqual({ selectedIds: ['a', 'b'], primaryId: 'b' });

    const cloned = selection.clone();
    expect(cloned.getSelection()).toEqual(['a', 'b']);
    expect(cloned.getPrimaryId()).toBe('b');

    cloned.select('c');
    expect(selection.getSelection()).toEqual(['a', 'b']); // Isolated instance
  });
});

describe('Studio Layers Module', () => {
  interface TestItem extends LayerItem {
    name: string;
  }

  const sampleItems: TestItem[] = [
    { id: 'a', name: 'Layer A' },
    { id: 'b', name: 'Layer B' },
    { id: 'c', name: 'Layer C' },
    { id: 'd', name: 'Layer D' },
    { id: 'e', name: 'Layer E' },
  ];

  it('bringToFront moves selected items to top preserving relative order', () => {
    const result = bringToFront(sampleItems, ['b', 'd']);
    expect(result.map((i) => i.id)).toEqual(['a', 'c', 'e', 'b', 'd']);
    expect(sampleItems.map((i) => i.id)).toEqual(['a', 'b', 'c', 'd', 'e']); // Immutable
  });

  it('sendToBack moves selected items to bottom preserving relative order', () => {
    const result = sendToBack(sampleItems, ['b', 'd']);
    expect(result.map((i) => i.id)).toEqual(['b', 'd', 'a', 'c', 'e']);
  });

  it('moveUp steps selected items toward front stably without leapfrogging', () => {
    // a(0), b(1), c(2), d(3), e(4) -> select b(1), d(3) -> b swaps with c, d swaps with e
    const step1 = moveUp(sampleItems, ['b', 'd']);
    expect(step1.map((i) => i.id)).toEqual(['a', 'c', 'b', 'e', 'd']);

    // d is already at top (index 4); moving up again moves b up into e slot
    const step2 = moveUp(step1, ['b', 'd']);
    expect(step2.map((i) => i.id)).toEqual(['a', 'c', 'e', 'b', 'd']);

    // Both b and d are at top; moving up again is a no-op
    const step3 = moveUp(step2, ['b', 'd']);
    expect(step3.map((i) => i.id)).toEqual(['a', 'c', 'e', 'b', 'd']);
  });

  it('moveDown steps selected items toward back stably without leapfrogging', () => {
    // a(0), b(1), c(2), d(3), e(4) -> select b(1), d(3) -> b swaps with a, d swaps with c
    const step1 = moveDown(sampleItems, ['b', 'd']);
    expect(step1.map((i) => i.id)).toEqual(['b', 'a', 'd', 'c', 'e']);

    // b is at index 0; moving down again moves d down into a slot
    const step2 = moveDown(step1, ['b', 'd']);
    expect(step2.map((i) => i.id)).toEqual(['b', 'd', 'a', 'c', 'e']);
  });

  it('reorder and moveItemsToIndex operate immutably', () => {
    const reordered = reorder(sampleItems, 0, 3);
    expect(reordered.map((i) => i.id)).toEqual(['b', 'c', 'd', 'a', 'e']);

    const movedBlock = moveItemsToIndex(sampleItems, ['b', 'd'], 1);
    expect(movedBlock.map((i) => i.id)).toEqual(['a', 'b', 'd', 'c', 'e']);
  });

  it('handles locking and unlocked item filtering', () => {
    const locked = setLocked(sampleItems, ['b', 'c'], true);
    expect(isLocked(locked[1])).toBe(true);
    expect(isLocked(locked[2])).toBe(true);
    expect(isLocked(locked[0])).toBe(false);

    const unlockedIds = filterUnlocked(locked, ['a', 'b', 'c', 'd']);
    expect(unlockedIds).toEqual(['a', 'd']);

    const toggled = toggleLocked(locked, ['b']);
    expect(isLocked(toggled[1])).toBe(false);
  });

  it('StudioLayers class provides convenient stateful wrapper', () => {
    const layers = createStudioLayers(sampleItems);
    layers.bringToFront(['a']);
    expect(layers.getItems().map((i) => i.id)).toEqual(['b', 'c', 'd', 'e', 'a']);

    const zMap = layers.getZIndexMap();
    expect(zMap.get('b')).toBe(0);
    expect(zMap.get('a')).toBe(4);
  });
});

describe('Studio Keymap Module', () => {
  function createKeyEvent(options: Partial<KeyboardEvent> = {}): KeyboardEvent {
    return {
      key: options.key ?? 'a',
      ctrlKey: options.ctrlKey ?? false,
      metaKey: options.metaKey ?? false,
      shiftKey: options.shiftKey ?? false,
      altKey: options.altKey ?? false,
      repeat: options.repeat ?? false,
      isComposing: options.isComposing ?? false,
      keyCode: options.keyCode ?? 0,
      preventDefault: vi.fn(),
      stopPropagation: vi.fn(),
      target: options.target ?? document.createElement('div'),
    } as unknown as KeyboardEvent;
  }

  describe('isInputFocused', () => {
    it('returns true for input, textarea, and select elements', () => {
      const input = document.createElement('input');
      expect(isInputFocused({ target: input })).toBe(true);

      const textarea = document.createElement('textarea');
      expect(isInputFocused({ target: textarea })).toBe(true);

      const select = document.createElement('select');
      expect(isInputFocused({ target: select })).toBe(true);
    });

    it('returns true for contenteditable elements and textbox roles', () => {
      const div = document.createElement('div');
      div.setAttribute('contenteditable', 'true');
      expect(isInputFocused({ target: div })).toBe(true);

      const roleBox = document.createElement('div');
      roleBox.setAttribute('role', 'textbox');
      expect(isInputFocused({ target: roleBox })).toBe(true);
    });

    it('returns false for canvas, stage, and body elements', () => {
      const canvas = document.createElement('canvas');
      expect(isInputFocused({ target: canvas })).toBe(false);

      const div = document.createElement('div');
      expect(isInputFocused({ target: div })).toBe(false);
    });
  });

  describe('isMod and matchKeyBinding', () => {
    it('detects platform Mod key', () => {
      expect(isMod(createKeyEvent({ ctrlKey: true }))).toBe(true);
      expect(isMod(createKeyEvent({ metaKey: true }))).toBe(true);
      expect(isMod(createKeyEvent({ ctrlKey: false, metaKey: false }))).toBe(false);
    });

    it('matches undo, redo, delete, selectAll, and nudge commands', () => {
      const undoEvent = createKeyEvent({ key: 'z', ctrlKey: true });
      expect(matchKeyBinding(undoEvent)?.command).toBe('undo');

      const redoEvent1 = createKeyEvent({ key: 'z', ctrlKey: true, shiftKey: true });
      expect(matchKeyBinding(redoEvent1)?.command).toBe('redo');

      const redoEvent2 = createKeyEvent({ key: 'y', ctrlKey: true });
      expect(matchKeyBinding(redoEvent2)?.command).toBe('redo');

      const selectAllEvent = createKeyEvent({ key: 'a', ctrlKey: true });
      expect(matchKeyBinding(selectAllEvent)?.command).toBe('selectAll');

      const deleteEvent = createKeyEvent({ key: 'Delete' });
      expect(matchKeyBinding(deleteEvent)?.command).toBe('delete');

      const nudgeEvent = createKeyEvent({ key: 'ArrowUp' });
      expect(matchKeyBinding(nudgeEvent)?.command).toBe('nudgeUp');

      const largeNudgeEvent = createKeyEvent({ key: 'ArrowLeft', shiftKey: true });
      expect(matchKeyBinding(largeNudgeEvent)?.command).toBe('nudgeLeftLarge');
    });
  });

  describe('handleKeyDown routing and isolation', () => {
    it('isolates input fields from firing shortcuts', () => {
      const input = document.createElement('input');
      const event = createKeyEvent({ key: 'Delete', target: input });
      const deleteHandler = vi.fn();

      const handled = handleKeyDown(event, { delete: deleteHandler });
      expect(handled).toBe(false);
      expect(deleteHandler).not.toHaveBeenCalled();
      expect(event.preventDefault).not.toHaveBeenCalled();
    });

    it('ignores IME composition events', () => {
      const event = createKeyEvent({ key: 'z', ctrlKey: true, isComposing: true });
      const undoHandler = vi.fn();

      const handled = handleKeyDown(event, { undo: undoHandler });
      expect(handled).toBe(false);
      expect(undoHandler).not.toHaveBeenCalled();
    });

    it('invokes handler and prevents browser default on match', () => {
      const event = createKeyEvent({ key: 'z', ctrlKey: true });
      const undoHandler = vi.fn();

      const handled = handleKeyDown(event, { undo: undoHandler });
      expect(handled).toBe(true);
      expect(undoHandler).toHaveBeenCalled();
      expect(event.preventDefault).toHaveBeenCalled();
    });

    it('does not prevent default when command is unhandled', () => {
      const event = createKeyEvent({ key: 'd', ctrlKey: true }); // duplicate command
      // No duplicate handler registered
      const handled = handleKeyDown(event, {});
      expect(handled).toBe(false);
      expect(event.preventDefault).not.toHaveBeenCalled();
    });

    it('guards against repeat for action commands while permitting repeat for nudge', () => {
      const repeatDelete = createKeyEvent({ key: 'Delete', repeat: true });
      const deleteHandler = vi.fn();
      expect(handleKeyDown(repeatDelete, { delete: deleteHandler })).toBe(false);
      expect(deleteHandler).not.toHaveBeenCalled();

      const repeatNudge = createKeyEvent({ key: 'ArrowDown', repeat: true });
      const nudgeHandler = vi.fn();
      expect(handleKeyDown(repeatNudge, { nudgeDown: nudgeHandler })).toBe(true);
      expect(nudgeHandler).toHaveBeenCalled();
    });

    it('StudioKeymap class handles registration and dispatch', () => {
      const keymap = createStudioKeymap();
      const undoFn = vi.fn();
      keymap.setHandlers({ undo: undoFn });

      const event = createKeyEvent({ key: 'z', ctrlKey: true });
      expect(keymap.handle(event)).toBe(true);
      expect(undoFn).toHaveBeenCalled();
    });
  });
});

describe('Studio Viewport Module', () => {
  describe('clampZoom', () => {
    it('clamps zoom between min (0.25) and max (3.0)', () => {
      expect(clampZoom(0.1)).toBe(0.25);
      expect(clampZoom(3.5)).toBe(3.0);
      expect(clampZoom(1.0)).toBe(1.0);
      expect(clampZoom(0.25)).toBe(0.25);
      expect(clampZoom(3.0)).toBe(3.0);
    });

    it('supports custom min and max zoom limits', () => {
      expect(clampZoom(0.5, 0.6, 2.0)).toBe(0.6);
      expect(clampZoom(2.5, 0.6, 2.0)).toBe(2.0);
      expect(clampZoom(1.5, 0.6, 2.0)).toBe(1.5);
    });
  });

  describe('calculateWheelZoomFactor', () => {
    it('returns zoom in factor (> 1) on negative deltaY (scrolling up)', () => {
      const factor = calculateWheelZoomFactor(-100, 0.1);
      expect(factor).toBeCloseTo(1.1, 4);
    });

    it('returns zoom out factor (< 1) on positive deltaY (scrolling down)', () => {
      const factor = calculateWheelZoomFactor(100, 0.1);
      expect(factor).toBeCloseTo(1 / 1.1, 4);
    });
  });

  describe('calculateCursorZoom', () => {
    it('calculates cursor-anchored zoom preserving exact cursor position on canvas', () => {
      // Cursor at (300, 200), initial pan at (0, 0), zoom 1.0 -> 2.0
      const result = calculateCursorZoom({
        cursorX: 300,
        cursorY: 200,
        panX: 0,
        panY: 0,
        scaleOld: 1.0,
        scaleNew: 2.0,
      });

      // x_new = 300 - (300 - 0) * (2.0 / 1.0) = 300 - 600 = -300
      // y_new = 200 - (200 - 0) * (2.0 / 1.0) = 200 - 400 = -200
      expect(result.panX).toBe(-300);
      expect(result.panY).toBe(-200);
      expect(result.scale).toBe(2.0);
    });

    it('calculates cursor-anchored zoom with existing non-zero pan offset', () => {
      // Cursor at (250, 150), existing pan at (-50, -30), zoom 1.5 -> 3.0
      const result = calculateCursorZoom({
        cursorX: 250,
        cursorY: 150,
        panX: -50,
        panY: -30,
        scaleOld: 1.5,
        scaleNew: 3.0,
      });

      // ratio = 3.0 / 1.5 = 2.0
      // x_new = 250 - (250 - (-50)) * 2.0 = 250 - 300 * 2 = 250 - 600 = -350
      // y_new = 150 - (150 - (-30)) * 2.0 = 150 - 180 * 2 = 150 - 360 = -210
      expect(result.panX).toBe(-350);
      expect(result.panY).toBe(-210);
      expect(result.scale).toBe(3.0);
    });

    it('handles zooming out centered at cursor', () => {
      const result = calculateCursorZoom({
        cursorX: 400,
        cursorY: 300,
        panX: -100,
        panY: -50,
        scaleOld: 2.0,
        scaleNew: 1.0,
      });

      // ratio = 1.0 / 2.0 = 0.5
      // x_new = 400 - (400 - (-100)) * 0.5 = 400 - 500 * 0.5 = 400 - 250 = 150
      // y_new = 300 - (300 - (-50)) * 0.5 = 300 - 350 * 0.5 = 300 - 175 = 125
      expect(result.panX).toBe(150);
      expect(result.panY).toBe(125);
      expect(result.scale).toBe(1.0);
    });

    it('safely handles scaleOld <= 0 fallback', () => {
      const result = calculateCursorZoom({
        cursorX: 100,
        cursorY: 100,
        panX: 10,
        panY: 20,
        scaleOld: 0,
        scaleNew: 1.0,
      });
      expect(result.panX).toBe(10);
      expect(result.panY).toBe(20);
      expect(result.scale).toBe(1.0);
    });
  });

  describe('fitViewport', () => {
    it('calculates fit scale factoring in gutters and aspect ratio', () => {
      // Viewport: 1000 x 800, Gutter: 36 (available: 964 x 764)
      // Page: 500 x 700 px
      // Width ratio: 964 / 500 = 1.928
      // Height ratio: 764 / 700 = 1.0914
      // Scale should be height-constrained (1.0914)
      const scale = fitViewport({
        viewportWidth: 1000,
        viewportHeight: 800,
        pageWidthPx: 500,
        pageHeightPx: 700,
        minZoom: 0.25,
        maxZoom: 3.0,
        gutter: 36,
      });
      expect(scale).toBeCloseTo(764 / 700, 4);
    });

    it('clamps fit scale to min and max zoom', () => {
      const minFit = fitViewport({
        viewportWidth: 100,
        viewportHeight: 100,
        pageWidthPx: 1000,
        pageHeightPx: 1000,
        minZoom: 0.25,
        maxZoom: 3.0,
      });
      expect(minFit).toBe(0.25);

      const maxFit = fitViewport({
        viewportWidth: 5000,
        viewportHeight: 5000,
        pageWidthPx: 100,
        pageHeightPx: 100,
        minZoom: 0.25,
        maxZoom: 3.0,
      });
      expect(maxFit).toBe(3.0);
    });
  });
});
