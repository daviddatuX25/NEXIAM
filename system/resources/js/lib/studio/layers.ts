/**
 * Pure layer ordering and z-index management for document studio components.
 * Preserves stable relative ordering for multi-item operations and never mutates input.
 * Zero domain, framework, or rendering dependencies.
 */

import type { LayerItem } from './types.js';
export type { LayerItem } from './types.js';

export type DropPosition = 'above' | 'below';

/**
 * Moves all items matching ids to the end of the array (top of DOM/visual stack).
 * Preserves relative order of selected items and unselected items.
 */
export function bringToFront<T extends { id: string }>(
  items: readonly T[],
  ids: readonly string[]
): T[] {
  if (items.length <= 1 || ids.length === 0) {
    return items.slice();
  }

  const idSet = new Set(ids);
  const unselected: T[] = [];
  const selected: T[] = [];

  for (const item of items) {
    if (idSet.has(item.id)) {
      selected.push(item);
    } else {
      unselected.push(item);
    }
  }

  return [...unselected, ...selected];
}

/**
 * Moves all items matching ids to the start of the array (bottom of DOM/visual stack).
 * Preserves relative order of selected items and unselected items.
 */
export function sendToBack<T extends { id: string }>(
  items: readonly T[],
  ids: readonly string[]
): T[] {
  if (items.length <= 1 || ids.length === 0) {
    return items.slice();
  }

  const idSet = new Set(ids);
  const unselected: T[] = [];
  const selected: T[] = [];

  for (const item of items) {
    if (idSet.has(item.id)) {
      selected.push(item);
    } else {
      unselected.push(item);
    }
  }

  return [...selected, ...unselected];
}

/**
 * Moves selected items one position toward the front (higher index).
 * Stable step algorithm: avoids items leapfrogging other selected items.
 */
export function moveUp<T extends { id: string }>(
  items: readonly T[],
  ids: readonly string[]
): T[] {
  if (items.length <= 1 || ids.length === 0) {
    return items.slice();
  }

  const result = items.slice();
  const idSet = new Set(ids);

  // Iterate top-down so we move elements upwards into empty unselected slots
  for (let i = result.length - 2; i >= 0; i--) {
    if (idSet.has(result[i].id) && !idSet.has(result[i + 1].id)) {
      const temp = result[i];
      result[i] = result[i + 1];
      result[i + 1] = temp;
    }
  }

  return result;
}

/**
 * Moves selected items one position toward the back (lower index).
 * Stable step algorithm: avoids items leapfrogging other selected items.
 */
export function moveDown<T extends { id: string }>(
  items: readonly T[],
  ids: readonly string[]
): T[] {
  if (items.length <= 1 || ids.length === 0) {
    return items.slice();
  }

  const result = items.slice();
  const idSet = new Set(ids);

  // Iterate bottom-up so we move elements downwards into empty unselected slots
  for (let i = 1; i < result.length; i++) {
    if (idSet.has(result[i].id) && !idSet.has(result[i - 1].id)) {
      const temp = result[i];
      result[i] = result[i - 1];
      result[i - 1] = temp;
    }
  }

  return result;
}

/**
 * Reorders a single item from fromIndex to toIndex immutably.
 */
export function reorder<T>(items: readonly T[], fromIndex: number, toIndex: number): T[] {
  if (
    fromIndex < 0 ||
    fromIndex >= items.length ||
    toIndex < 0 ||
    toIndex >= items.length ||
    fromIndex === toIndex
  ) {
    return items.slice();
  }

  const result = items.slice();
  const [removed] = result.splice(fromIndex, 1);
  result.splice(toIndex, 0, removed);
  return result;
}

/**
 * Moves multiple items to a target index while preserving relative order
 * of both selected items and unselected items.
 */
export function moveItemsToIndex<T extends { id: string }>(
  items: readonly T[],
  ids: readonly string[],
  targetIndex: number
): T[] {
  if (items.length <= 1 || ids.length === 0) {
    return items.slice();
  }

  const idSet = new Set(ids);
  const selected: T[] = [];
  const unselectedBefore: T[] = [];
  const unselectedAfter: T[] = [];

  const clampedTarget = Math.max(0, Math.min(targetIndex, items.length));

  let unselectedIndex = 0;
  for (const item of items) {
    if (idSet.has(item.id)) {
      selected.push(item);
    } else {
      if (unselectedIndex < clampedTarget) {
        unselectedBefore.push(item);
      } else {
        unselectedAfter.push(item);
      }
      unselectedIndex++;
    }
  }

  return [...unselectedBefore, ...selected, ...unselectedAfter];
}

/**
 * Immutably updates the locked state of items matching ids.
 */
export function setLocked<T extends { id: string; locked?: boolean }>(
  items: readonly T[],
  ids: readonly string[],
  locked: boolean
): T[] {
  if (items.length === 0 || ids.length === 0) {
    return items.slice();
  }

  const idSet = new Set(ids);
  return items.map((item) => {
    if (idSet.has(item.id)) {
      return { ...item, locked };
    }
    return item;
  });
}

/**
 * Immutably toggles the locked state of items matching ids.
 */
export function toggleLocked<T extends { id: string; locked?: boolean }>(
  items: readonly T[],
  ids: readonly string[]
): T[] {
  if (items.length === 0 || ids.length === 0) {
    return items.slice();
  }

  const idSet = new Set(ids);
  return items.map((item) => {
    if (idSet.has(item.id)) {
      return { ...item, locked: !item.locked };
    }
    return item;
  });
}

/**
 * Returns true if an item is locked.
 */
export function isLocked<T extends { locked?: boolean }>(item: T): boolean {
  return Boolean(item.locked);
}

/**
 * Filters a list of IDs to only those whose items are not locked.
 */
export function filterUnlocked<T extends { id: string; locked?: boolean }>(
  items: readonly T[],
  ids: readonly string[]
): string[] {
  const itemMap = new Map<string, T>();
  for (const item of items) {
    itemMap.set(item.id, item);
  }

  return ids.filter((id) => {
    const item = itemMap.get(id);
    return item ? !item.locked : true;
  });
}

/**
 * Returns a Map from item ID to its 0-based z-index (array index).
 */
export function getZIndexMap<T extends { id: string }>(
  items: readonly T[]
): Map<string, number> {
  const map = new Map<string, number>();
  for (let i = 0; i < items.length; i++) {
    map.set(items[i].id, i);
  }
  return map;
}

/**
 * Finds the layer index for a given item ID.
 */
export function findLayerIndex<T extends { id: string }>(
  items: readonly T[],
  id: string
): number {
  return items.findIndex((item) => item.id === id);
}

/**
 * State container wrapper for layer operations.
 */
export class StudioLayers<T extends LayerItem = LayerItem> {
  private items: T[];

  constructor(initialItems: readonly T[] = []) {
    this.items = initialItems.slice();
  }

  public getItems(): T[] {
    return this.items.slice();
  }

  public setItems(items: readonly T[]): this {
    this.items = items.slice();
    return this;
  }

  public bringToFront(ids: readonly string[]): T[] {
    this.items = bringToFront(this.items, ids);
    return this.getItems();
  }

  public sendToBack(ids: readonly string[]): T[] {
    this.items = sendToBack(this.items, ids);
    return this.getItems();
  }

  public moveUp(ids: readonly string[]): T[] {
    this.items = moveUp(this.items, ids);
    return this.getItems();
  }

  public moveDown(ids: readonly string[]): T[] {
    this.items = moveDown(this.items, ids);
    return this.getItems();
  }

  public reorder(fromIndex: number, toIndex: number): T[] {
    this.items = reorder(this.items, fromIndex, toIndex);
    return this.getItems();
  }

  public setLocked(ids: readonly string[], locked: boolean): T[] {
    this.items = setLocked(this.items, ids, locked);
    return this.getItems();
  }

  public toggleLocked(ids: readonly string[]): T[] {
    this.items = toggleLocked(this.items, ids);
    return this.getItems();
  }

  public getZIndexMap(): Map<string, number> {
    return getZIndexMap(this.items);
  }
}

/**
 * Factory helper for creating StudioLayers instances.
 */
export function createStudioLayers<T extends LayerItem = LayerItem>(
  initialItems?: readonly T[]
): StudioLayers<T> {
  return new StudioLayers<T>(initialItems);
}

/**
 * Converts canonical z-index (bottom to top) to the reversed visual row index.
 */
export function canonicalToVisualIndex(canonicalIndex: number, totalCount: number): number {
  if (totalCount <= 0 || canonicalIndex < 0 || canonicalIndex >= totalCount) {
    return -1;
  }

  return totalCount - 1 - canonicalIndex;
}

/**
 * Converts a reversed visual row index back to canonical z-index.
 */
export function visualToCanonicalIndex(visualIndex: number, totalCount: number): number {
  if (totalCount <= 0 || visualIndex < 0 || visualIndex >= totalCount) {
    return -1;
  }

  return totalCount - 1 - visualIndex;
}

export function getVisualLayers<T extends LayerItem>(items: readonly T[]): T[] {
  return [...items].reverse();
}

export function reorderLayers<T extends LayerItem>(
  items: T[],
  movableIds: string | string[],
  targetId: string,
  position: DropPosition = 'above',
): T[] {
  const ids = Array.isArray(movableIds) ? movableIds : [movableIds];
  const idSet = new Set(ids);
  const movable = items.filter((item) => idSet.has(item.id) && !item.locked);

  if (movable.length === 0) {
    return items;
  }

  const movableSet = new Set(movable.map((item) => item.id));

  if (movableSet.has(targetId)) {
    return items;
  }

  const remaining = items.filter((item) => !movableSet.has(item.id));
  const targetIndex = remaining.findIndex((item) => item.id === targetId);

  if (targetIndex < 0) {
    return items;
  }

  const insertIndex = position === 'above' ? targetIndex + 1 : targetIndex;

  return [
    ...remaining.slice(0, insertIndex),
    ...movable,
    ...remaining.slice(insertIndex),
  ];
}

export function moveLayerUp<T extends LayerItem>(items: T[], ids: string | string[]): T[] {
  const idSet = new Set(Array.isArray(ids) ? ids : [ids]);
  const movableSet = new Set(
    items.filter((item) => idSet.has(item.id) && !item.locked).map((item) => item.id),
  );

  if (movableSet.size === 0) {
    return items;
  }

  const result = [...items];

  for (let index = result.length - 2; index >= 0; index -= 1) {
    if (movableSet.has(result[index].id) && !movableSet.has(result[index + 1].id)) {
      [result[index], result[index + 1]] = [result[index + 1], result[index]];
    }
  }

  return result;
}

export function moveLayerDown<T extends LayerItem>(items: T[], ids: string | string[]): T[] {
  const idSet = new Set(Array.isArray(ids) ? ids : [ids]);
  const movableSet = new Set(
    items.filter((item) => idSet.has(item.id) && !item.locked).map((item) => item.id),
  );

  if (movableSet.size === 0) {
    return items;
  }

  const result = [...items];

  for (let index = 1; index < result.length; index += 1) {
    if (movableSet.has(result[index].id) && !movableSet.has(result[index - 1].id)) {
      [result[index], result[index - 1]] = [result[index - 1], result[index]];
    }
  }

  return result;
}

export function bringLayerToFront<T extends LayerItem>(items: T[], ids: string | string[]): T[] {
  const idSet = new Set(Array.isArray(ids) ? ids : [ids]);
  const movable = items.filter((item) => idSet.has(item.id) && !item.locked);

  if (movable.length === 0) {
    return items;
  }

  const movableSet = new Set(movable.map((item) => item.id));
  const remaining = items.filter((item) => !movableSet.has(item.id));

  return [...remaining, ...movable];
}

export function sendLayerToBack<T extends LayerItem>(items: T[], ids: string | string[]): T[] {
  const idSet = new Set(Array.isArray(ids) ? ids : [ids]);
  const movable = items.filter((item) => idSet.has(item.id) && !item.locked);

  if (movable.length === 0) {
    return items;
  }

  const movableSet = new Set(movable.map((item) => item.id));
  const remaining = items.filter((item) => !movableSet.has(item.id));

  return [...movable, ...remaining];
}

export function setLayerLocked<T extends LayerItem>(
  items: T[],
  ids: string | string[],
  locked: boolean,
): T[] {
  const idSet = new Set(Array.isArray(ids) ? ids : [ids]);

  return items.map((item) => (idSet.has(item.id) ? { ...item, locked } : item));
}

export function isLayerLocked<T extends LayerItem>(item: T): boolean {
  return Boolean(item.locked);
}
