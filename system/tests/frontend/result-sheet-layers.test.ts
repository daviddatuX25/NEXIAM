// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ResultSheetLayersPanel from '../../resources/js/Components/ResultSheet/ResultSheetLayersPanel.svelte';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';
import {
  bringLayerToFront,
  canonicalToVisualIndex,
  getVisualLayers,
  type LayerItem,
  moveLayerDown,
  moveLayerUp,
  reorderLayers,
  sendLayerToBack,
  visualToCanonicalIndex,
} from '../../resources/js/lib/studio/layers';

describe('Layer Index Translation & Pure Mathematical Invariants', () => {
  it('translates canonical z-index to visual list index and vice versa', () => {
    // 4 items: canonical [0, 1, 2, 3] -> visual [3, 2, 1, 0]
    expect(canonicalToVisualIndex(3, 4)).toBe(0); // Top layer -> Visual 1st
    expect(canonicalToVisualIndex(2, 4)).toBe(1);
    expect(canonicalToVisualIndex(1, 4)).toBe(2);
    expect(canonicalToVisualIndex(0, 4)).toBe(3); // Bottom layer -> Visual last

    expect(visualToCanonicalIndex(0, 4)).toBe(3);
    expect(visualToCanonicalIndex(1, 4)).toBe(2);
    expect(visualToCanonicalIndex(2, 4)).toBe(1);
    expect(visualToCanonicalIndex(3, 4)).toBe(0);

    // Boundary / invalid handling
    expect(canonicalToVisualIndex(-1, 4)).toBe(-1);
    expect(canonicalToVisualIndex(4, 4)).toBe(-1);
    expect(visualToCanonicalIndex(-1, 4)).toBe(-1);
    expect(visualToCanonicalIndex(4, 4)).toBe(-1);
  });

  it('getVisualLayers returns reversed array for visual list rendering', () => {
    const canonical: LayerItem[] = [
      { id: 'layer-bottom', name: 'Background' },
      { id: 'layer-mid', name: 'Table Grid' },
      { id: 'layer-top', name: 'Header Title' },
    ];
    const visual = getVisualLayers(canonical);
    expect(visual.map((l) => l.id)).toEqual(['layer-top', 'layer-mid', 'layer-bottom']);
    // Original canonical array is not mutated
    expect(canonical[0].id).toBe('layer-bottom');
  });

  it('reorders first visual item (top z-index) to drop below last visual item (bottom z-index)', () => {
    // Canonical [A, B, C, D] -> Visual [D, C, B, A]
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B' },
      { id: 'C', name: 'Layer C' },
      { id: 'D', name: 'Layer D' },
    ];

    // Drag visual D (first visual) and drop visually 'below' visual A (last visual)
    const result = reorderLayers(items, 'D', 'A', 'below');
    // D is now lowest z-index (canonical index 0)
    expect(result.map((i) => i.id)).toEqual(['D', 'A', 'B', 'C']);
    expect(getVisualLayers(result).map((i) => i.id)).toEqual(['C', 'B', 'A', 'D']);
  });

  it('reorders last visual item (bottom z-index) to drop above first visual item (top z-index)', () => {
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B' },
      { id: 'C', name: 'Layer C' },
      { id: 'D', name: 'Layer D' },
    ];

    // Drag visual A (last visual) and drop visually 'above' visual D (first visual)
    const result = reorderLayers(items, 'A', 'D', 'above');
    // A is now highest z-index (canonical index 3)
    expect(result.map((i) => i.id)).toEqual(['B', 'C', 'D', 'A']);
    expect(getVisualLayers(result).map((i) => i.id)).toEqual(['A', 'D', 'C', 'B']);
  });

  it('reorders middle visual item above/below another middle visual item', () => {
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B' },
      { id: 'C', name: 'Layer C' },
      { id: 'D', name: 'Layer D' },
    ];

    // Visual: [D, C, B, A]. Drag C (visual index 1) and drop below B (visual index 2)
    const result1 = reorderLayers(items, 'C', 'B', 'below');
    expect(result1.map((i) => i.id)).toEqual(['A', 'C', 'B', 'D']);
    expect(getVisualLayers(result1).map((i) => i.id)).toEqual(['D', 'B', 'C', 'A']);

    // Visual: [D, C, B, A]. Drag B (visual index 2) and drop above C (visual index 1)
    const result2 = reorderLayers(items, 'B', 'C', 'above');
    expect(result2.map((i) => i.id)).toEqual(['A', 'C', 'B', 'D']);
    expect(getVisualLayers(result2).map((i) => i.id)).toEqual(['D', 'B', 'C', 'A']);
  });

  it('preserves stable relative order when dragging multiple selected items', () => {
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B' },
      { id: 'C', name: 'Layer C' },
      { id: 'D', name: 'Layer D' },
      { id: 'E', name: 'Layer E' },
    ];

    // Select [B, D]. Drag below A (bottom)
    const result = reorderLayers(items, ['B', 'D'], 'A', 'below');
    // Relative order of B and D is preserved: B then D
    expect(result.map((i) => i.id)).toEqual(['B', 'D', 'A', 'C', 'E']);
    expect(getVisualLayers(result).map((i) => i.id)).toEqual(['E', 'C', 'A', 'D', 'B']);
  });

  it('locked items are strictly filtered out and never move during reordering', () => {
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B', locked: true },
      { id: 'C', name: 'Layer C' },
      { id: 'D', name: 'Layer D' },
    ];

    // Attempting to drag locked item B is a no-op
    const resultLocked = reorderLayers(items, 'B', 'D', 'above');
    expect(resultLocked).toBe(items);
    expect(resultLocked.map((i) => i.id)).toEqual(['A', 'B', 'C', 'D']);

    // Attempting to drag [B (locked), C (unlocked)] only moves C
    const resultMixed = reorderLayers(items, ['B', 'C'], 'A', 'below');
    // C moved below A, while locked B remained in place relative to others
    expect(resultMixed.map((i) => i.id)).toEqual(['C', 'A', 'B', 'D']);

    // Locked target item remains in place when unlocked items move around it
    const resultAroundLocked = reorderLayers(items, 'D', 'B', 'below');
    expect(resultAroundLocked.map((i) => i.id)).toEqual(['A', 'D', 'B', 'C']);
  });

  it('moveLayerUp, moveLayerDown, bringLayerToFront, sendLayerToBack match drag semantics', () => {
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B' },
      { id: 'C', name: 'Layer C' },
      { id: 'D', name: 'Layer D' },
    ];

    // Move D down visually (Send Backward / -1 z-index)
    const movedDown = moveLayerDown(items, 'D');
    expect(movedDown.map((i) => i.id)).toEqual(['A', 'B', 'D', 'C']);
    // Equivalent to dragging D and dropping below C
    const dragEquivalent = reorderLayers(items, 'D', 'C', 'below');
    expect(movedDown.map((i) => i.id)).toEqual(dragEquivalent.map((i) => i.id));

    // Move B up visually (Bring Forward / +1 z-index)
    const movedUp = moveLayerUp(items, 'B');
    expect(movedUp.map((i) => i.id)).toEqual(['A', 'C', 'B', 'D']);
    const dragUpEquivalent = reorderLayers(items, 'B', 'C', 'above');
    expect(movedUp.map((i) => i.id)).toEqual(dragUpEquivalent.map((i) => i.id));

    // Bring A to Front (top of visual list / highest canonical index)
    const toFront = bringLayerToFront(items, 'A');
    expect(toFront.map((i) => i.id)).toEqual(['B', 'C', 'D', 'A']);
    expect(reorderLayers(items, 'A', 'D', 'above').map((i) => i.id)).toEqual(
      toFront.map((i) => i.id)
    );

    // Send D to Back (bottom of visual list / lowest canonical index)
    const toBack = sendLayerToBack(items, 'D');
    expect(toBack.map((i) => i.id)).toEqual(['D', 'A', 'B', 'C']);
    expect(reorderLayers(items, 'D', 'A', 'below').map((i) => i.id)).toEqual(
      toBack.map((i) => i.id)
    );
  });

  it('locked items never move when executing moveUp, moveDown, bringToFront, sendToBack', () => {
    const items: LayerItem[] = [
      { id: 'A', name: 'Layer A' },
      { id: 'B', name: 'Layer B', locked: true },
      { id: 'C', name: 'Layer C' },
    ];

    expect(moveLayerUp(items, 'B')).toBe(items);
    expect(moveLayerDown(items, 'B')).toBe(items);
    expect(bringLayerToFront(items, 'B')).toBe(items);
    expect(sendLayerToBack(items, 'B')).toBe(items);
  });
});

describe('Rendered ResultSheetLayersPanel Svelte Component', () => {
  const sampleItems: LayerItem[] = [
    { id: 'layer-1', name: 'Watermark', type: 'image' },
    { id: 'layer-2', name: 'Score Table', type: 'table' },
    { id: 'layer-3', name: 'Candidate QR', type: 'qr', locked: true },
    { id: 'layer-4', name: 'Header Title', type: 'text' },
  ];
  afterEach(() => {
    cleanup();
  });


  it('renders visual list in reversed order with accurate layer Z-order numbers', () => {
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-4'],
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    expect(rows.length).toBe(4);

    // Row 0 (top of visual list) is Layer 4 (Header Title, canonical index 3)
    expect(rows[0].getAttribute('data-layer-id')).toBe('layer-4');
    expect(rows[0].textContent).toContain('Header Title');
    expect(rows[0].textContent).toContain('4');

    // Row 1 is Layer 3 (Candidate QR, canonical index 2, locked)
    expect(rows[1].getAttribute('data-layer-id')).toBe('layer-3');
    expect(rows[1].textContent).toContain('Candidate QR');
    expect(rows[1].textContent).toContain('3');

    // Row 2 is Layer 2 (Score Table, canonical index 1)
    expect(rows[2].getAttribute('data-layer-id')).toBe('layer-2');
    expect(rows[2].textContent).toContain('Score Table');
    expect(rows[2].textContent).toContain('2');

    // Row 3 (bottom of visual list) is Layer 1 (Watermark, canonical index 0)
    expect(rows[3].getAttribute('data-layer-id')).toBe('layer-1');
    expect(rows[3].textContent).toContain('Watermark');
    expect(rows[3].textContent).toContain('1');
  });

  it('drags first visual item and drops below last visual item, emitting exact canonical order', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const firstRow = rows[0]; // layer-4 (top visual)
    const lastRow = rows[3]; // layer-1 (bottom visual)

    // Mock getBoundingClientRect for drop calculation
    lastRow.getBoundingClientRect = () =>
      ({
        top: 100,
        bottom: 140,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 100,
        toJSON: () => {},
      }) as DOMRect;

    // Start drag on layer-4
    await fireEvent.dragStart(firstRow);

    // Drag over bottom half of layer-1 (clientY = 130 > midY 120 -> 'below')
    const dragOverEvt = new Event('dragover', { bubbles: true, cancelable: true });
    Object.defineProperty(dragOverEvt, 'clientY', { value: 130 });
    lastRow.dispatchEvent(dragOverEvt);

    // Drop on layer-1
    await fireEvent.drop(lastRow);
    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-4 placed at canonical index 0 (bottom)
    expect(reordered.map((i) => i.id)).toEqual(['layer-4', 'layer-1', 'layer-2', 'layer-3']);
  });

  it('drags last visual item and drops above first visual item, emitting exact canonical order', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const firstRow = rows[0]; // layer-4
    const lastRow = rows[3]; // layer-1

    firstRow.getBoundingClientRect = () =>
      ({
        top: 0,
        bottom: 40,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 0,
        toJSON: () => {},
      }) as DOMRect;

    await fireEvent.dragStart(lastRow);
    // clientY = 10 < midY 20 -> 'above'
    const dragOverEvt = new Event('dragover', { bubbles: true, cancelable: true });
    Object.defineProperty(dragOverEvt, 'clientY', { value: 10 });
    firstRow.dispatchEvent(dragOverEvt);
    await fireEvent.drop(firstRow);

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-1 placed at canonical index 3 (top)
    expect(reordered.map((i) => i.id)).toEqual(['layer-2', 'layer-3', 'layer-4', 'layer-1']);
  });

  it('drags middle visual item and drops above/below another middle visual item', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const midRow2 = rows[2]; // layer-2 (visual index 2)
    const firstRow = rows[0]; // layer-4 (visual index 0)

    firstRow.getBoundingClientRect = () =>
      ({
        top: 0,
        bottom: 40,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 0,
        toJSON: () => {},
      }) as DOMRect;

    await fireEvent.dragStart(midRow2);
    // clientY = 30 > midY 20 -> 'below'
    const dragOverEvt = new Event('dragover', { bubbles: true, cancelable: true });
    Object.defineProperty(dragOverEvt, 'clientY', { value: 30 });
    firstRow.dispatchEvent(dragOverEvt);
    await fireEvent.drop(firstRow);
    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // Visual order: layer-4, layer-2, layer-3, layer-1 -> Canonical: layer-1, layer-3, layer-2, layer-4
    expect(reordered.map((i) => i.id)).toEqual(['layer-1', 'layer-3', 'layer-2', 'layer-4']);
  });

  it('prevents drag on locked layer and does not emit reorder', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const lockedRow = rows[1]; // layer-3 (locked)

    expect(lockedRow.getAttribute('draggable')).toBe('false');

    await fireEvent.dragStart(lockedRow);
    expect(handleReorder).not.toHaveBeenCalled();
  });

  it('keyboard Alt+ArrowDown on top visual item moves it down visually (matching drag down)', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-4'],
      onReorder: handleReorder,
    });

    const firstRow = container.querySelector('[data-layer-id="layer-4"]');
    expect(firstRow).toBeTruthy();

    await fireEvent.keyDown(firstRow!, { key: 'ArrowDown', altKey: true });

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-4 swaps with layer-3 -> canonical: layer-1, layer-2, layer-4, layer-3
    expect(reordered.map((i) => i.id)).toEqual(['layer-1', 'layer-2', 'layer-4', 'layer-3']);
  });

  it('keyboard Alt+ArrowUp on lower visual item moves it up visually (matching drag up)', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-2'],
      onReorder: handleReorder,
    });

    const row2 = container.querySelector('[data-layer-id="layer-2"]');
    expect(row2).toBeTruthy();

    await fireEvent.keyDown(row2!, { key: 'ArrowUp', altKey: true });

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-2 swaps with layer-3 -> canonical: layer-1, layer-3, layer-2, layer-4
    expect(reordered.map((i) => i.id)).toEqual(['layer-1', 'layer-3', 'layer-2', 'layer-4']);
  });

  it('keyboard Alt+Shift+ArrowUp / Alt+Home brings item to front', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-1'],
      onReorder: handleReorder,
    });

    const row1 = container.querySelector('[data-layer-id="layer-1"]');
    await fireEvent.keyDown(row1!, { key: 'ArrowUp', altKey: true, shiftKey: true });

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    expect(reordered.map((i) => i.id)).toEqual(['layer-2', 'layer-3', 'layer-4', 'layer-1']);
  });

  it('keyboard Alt+Shift+ArrowDown / Alt+End sends item to back', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-4'],
      onReorder: handleReorder,
    });

    const row4 = container.querySelector('[data-layer-id="layer-4"]');
    await fireEvent.keyDown(row4!, { key: 'ArrowDown', altKey: true, shiftKey: true });

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    expect(reordered.map((i) => i.id)).toEqual(['layer-4', 'layer-1', 'layer-2', 'layer-3']);
  });

  it('keyboard shortcuts on locked layer do not move locked layer', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-3'],
      onReorder: handleReorder,
    });

    const lockedRow = container.querySelector('[data-layer-id="layer-3"]');
    await fireEvent.keyDown(lockedRow!, { key: 'ArrowUp', altKey: true });
    await fireEvent.keyDown(lockedRow!, { key: 'ArrowDown', altKey: true });
    await fireEvent.keyDown(lockedRow!, { key: 'ArrowUp', altKey: true, shiftKey: true });
    await fireEvent.keyDown(lockedRow!, { key: 'ArrowDown', altKey: true, shiftKey: true });

    expect(handleReorder).not.toHaveBeenCalled();
  });

  it('toolbar buttons are disabled when selected item is locked', () => {
    const { getByLabelText } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-3'], // layer-3 is locked
    });

    const bringToFrontBtn = getByLabelText('Bring selected layers to front') as HTMLButtonElement;
    const moveUpBtn = getByLabelText('Move selected layers up') as HTMLButtonElement;
    const moveDownBtn = getByLabelText('Move selected layers down') as HTMLButtonElement;
    const sendToBackBtn = getByLabelText('Send selected layers to back') as HTMLButtonElement;

    expect(bringToFrontBtn.disabled).toBe(true);
    expect(moveUpBtn.disabled).toBe(true);
    expect(moveDownBtn.disabled).toBe(true);
    expect(sendToBackBtn.disabled).toBe(true);
  });

  it('toolbar buttons move unlocked selected layer matching drag semantics', async () => {
    const handleReorder = vi.fn();
    const { getByLabelText } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-4'],
      onReorder: handleReorder,
    });

    const moveDownBtn = getByLabelText('Move selected layers down');
    await fireEvent.click(moveDownBtn);

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    expect(reordered.map((i) => i.id)).toEqual(['layer-1', 'layer-2', 'layer-4', 'layer-3']);
  });

  it('toggles lock when lock button is clicked or Alt+L is pressed', async () => {
    const handleToggleLock = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: ['layer-2'],
      onToggleLock: handleToggleLock,
    });

    const row2 = container.querySelector('[data-layer-id="layer-2"]');
    const lockBtn = row2?.querySelector('button[aria-label="Lock Score Table"]');
    expect(lockBtn).toBeTruthy();

    await fireEvent.click(lockBtn!);
    expect(handleToggleLock).toHaveBeenCalledWith('layer-2');

    await fireEvent.keyDown(row2!, { key: 'l', altKey: true });
    expect(handleToggleLock).toHaveBeenCalledWith('layer-2');
  });

  it('touch reordering alternative correctly tracks touch position and triggers reorder', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const firstRow = rows[0]; // layer-4
    const firstGrip = firstRow.querySelector('.cursor-grab');
    const lastRow = rows[3]; // layer-1

    // Mock elementFromPoint to return lastRow
    document.elementFromPoint = vi.fn().mockReturnValue(lastRow);
    lastRow.getBoundingClientRect = () =>
      ({
        top: 100,
        bottom: 140,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 100,
        toJSON: () => {},
      }) as DOMRect;

    // Touch start on grip
    await fireEvent.touchStart(firstGrip!, {
      touches: [{ clientX: 50, clientY: 20 } as unknown as Touch],
    });

    // Touch move over lastRow (clientY = 130 > midY 120 -> 'below')
    await fireEvent.touchMove(firstGrip!, {
      touches: [{ clientX: 50, clientY: 130 } as unknown as Touch],
    });

    // Touch end
    await fireEvent.touchEnd(firstGrip!);

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    expect(reordered.map((i) => i.id)).toEqual(['layer-4', 'layer-1', 'layer-2', 'layer-3']);
  });

  it('drags first visual item (layer-4) and drops above middle item (layer-2), emitting intended canonical order', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const firstRow = rows[0]; // layer-4
    const midRow = rows[2]; // layer-2 (middle visual item)

    midRow.getBoundingClientRect = () =>
      ({
        top: 80,
        bottom: 120,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 80,
        toJSON: () => {},
      }) as DOMRect;

    await fireEvent.dragStart(firstRow);
    // clientY = 90 < midY 100 -> 'above'
    const dragOverEvt = new Event('dragover', { bubbles: true, cancelable: true });
    Object.defineProperty(dragOverEvt, 'clientY', { value: 90 });
    midRow.dispatchEvent(dragOverEvt);
    await fireEvent.drop(midRow);

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-4 placed above layer-2 in visual order -> canonical: layer-1, layer-2, layer-4, layer-3
    expect(reordered.map((i) => i.id)).toEqual(['layer-1', 'layer-2', 'layer-4', 'layer-3']);
  });

  it('drags last visual item (layer-1) and drops below middle item (layer-3), emitting intended canonical order', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const lastRow = rows[3]; // layer-1
    const midRow = rows[1]; // layer-3

    midRow.getBoundingClientRect = () =>
      ({
        top: 40,
        bottom: 80,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 40,
        toJSON: () => {},
      }) as DOMRect;

    await fireEvent.dragStart(lastRow);
    // clientY = 70 > midY 60 -> 'below'
    const dragOverEvt = new Event('dragover', { bubbles: true, cancelable: true });
    Object.defineProperty(dragOverEvt, 'clientY', { value: 70 });
    midRow.dispatchEvent(dragOverEvt);
    await fireEvent.drop(midRow);

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-1 placed below layer-3 in visual order -> canonical: layer-2, layer-1, layer-3, layer-4
    expect(reordered.map((i) => i.id)).toEqual(['layer-2', 'layer-1', 'layer-3', 'layer-4']);
  });

  it('drags middle visual item (layer-2) and drops above first visual item (layer-4), emitting intended canonical order', async () => {
    const handleReorder = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      items: sampleItems,
      selectedIds: [],
      onReorder: handleReorder,
    });

    const rows = container.querySelectorAll('[data-layer-id]');
    const firstRow = rows[0]; // layer-4
    const midRow2 = rows[2]; // layer-2

    firstRow.getBoundingClientRect = () =>
      ({
        top: 0,
        bottom: 40,
        height: 40,
        left: 0,
        right: 200,
        width: 200,
        x: 0,
        y: 0,
        toJSON: () => {},
      }) as DOMRect;

    await fireEvent.dragStart(midRow2);
    // clientY = 10 < midY 20 -> 'above'
    const dragOverEvt = new Event('dragover', { bubbles: true, cancelable: true });
    Object.defineProperty(dragOverEvt, 'clientY', { value: 10 });
    firstRow.dispatchEvent(dragOverEvt);
    await fireEvent.drop(firstRow);

    expect(handleReorder).toHaveBeenCalledTimes(1);
    const reordered: LayerItem[] = handleReorder.mock.calls[0][0];
    // layer-2 placed above layer-4 -> canonical: layer-1, layer-3, layer-4, layer-2
    expect(reordered.map((i) => i.id)).toEqual(['layer-1', 'layer-3', 'layer-4', 'layer-2']);
  });

  it('Studio selection invariant: selected low layer component remains below high layer in DOM compiler order and has no z-20 or z-index elevation', () => {
    const studioComponents = [
      { id: 'comp-1', name: 'Background Frame', type: 'frame', x: 10, y: 10, width: 100, height: 100 },
      { id: 'comp-2', name: 'Middle Table', type: 'score_table', x: 20, y: 20, width: 80, height: 60 },
      { id: 'comp-3', name: 'Top QR', type: 'qr', x: 30, y: 30, width: 40, height: 40 },
    ];

    // Select comp-1 (lowest z-index in canonical array)
    const { container } = render(ResultSheetStudio, {
      components: studioComponents,
      activeComponentId: 'comp-1',
      activeComponentIds: ['comp-1'],
    });

    const renderedNodes = container.querySelectorAll('[data-component-id]');
    expect(renderedNodes.length).toBe(3);

    // Exact DOM order matches canonical array order: comp-1, comp-2, comp-3
    expect(renderedNodes[0].getAttribute('data-component-id')).toBe('comp-1');
    expect(renderedNodes[1].getAttribute('data-component-id')).toBe('comp-2');
    expect(renderedNodes[2].getAttribute('data-component-id')).toBe('comp-3');

    // Selected comp-1 receives selection ring but NO z-index override / z-20 class
    const selectedNode = renderedNodes[0];
    expect(selectedNode.className).toContain('ring-2');
    expect(selectedNode.className).toContain('ring-indigo-500');
    expect(selectedNode.className).not.toContain('z-20');
    expect(selectedNode.className).not.toContain('z-10');
    expect(selectedNode.className).not.toContain('z-50');

    // comp-1 remains before comp-3 in DOM paint order
    expect(renderedNodes[0].compareDocumentPosition(renderedNodes[2])).toBe(Node.DOCUMENT_POSITION_FOLLOWING);
  });
});
