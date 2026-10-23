/**
 * Multi-selection state machine with primary item tracking and marquee querying.
 * Zero domain, framework, or rendering dependencies.
 */

import { rectContains, rectIntersects } from './geometry.js';
import type {
  IdentifiedRect,
  MarqueeQueryMode,
  Rect,
  SelectionSerializedState,
  SelectionState,
} from './types.js';

export class StudioSelection {
  private selectedIds: Set<string>;
  private primaryId: string | null;

  constructor(initialIds: readonly string[] = [], initialPrimary: string | null = null) {
    this.selectedIds = new Set(initialIds);
    if (initialPrimary && this.selectedIds.has(initialPrimary)) {
      this.primaryId = initialPrimary;
    } else {
      this.primaryId = initialIds.length > 0 ? initialIds[initialIds.length - 1] : null;
    }
  }

  public get count(): number {
    return this.selectedIds.size;
  }

  public getCount(): number {
    return this.selectedIds.size;
  }

  public isEmpty(): boolean {
    return this.selectedIds.size === 0;
  }

  public hasSingle(): boolean {
    return this.selectedIds.size === 1;
  }

  public hasMultiple(): boolean {
    return this.selectedIds.size > 1;
  }

  public isSelected(id: string): boolean {
    return this.selectedIds.has(id);
  }

  public getPrimaryId(): string | null {
    return this.primaryId;
  }

  public setPrimaryId(id: string | null): boolean {
    if (id === null) {
      this.primaryId = null;
      return true;
    }
    if (this.selectedIds.has(id)) {
      this.primaryId = id;
      return true;
    }
    return false;
  }

  public getSelection(): string[] {
    return Array.from(this.selectedIds);
  }

  public getSelectedIds(): Set<string> {
    return new Set(this.selectedIds);
  }

  public getState(): SelectionState {
    return {
      selectedIds: new Set(this.selectedIds),
      primaryId: this.primaryId,
    };
  }

  /**
   * Selects a single item exclusively and sets it as primary.
   */
  public select(id: string): this {
    this.selectedIds.clear();
    this.selectedIds.add(id);
    this.primaryId = id;
    return this;
  }

  /**
   * Toggles an item in the selection.
   * If added, becomes the primary ID.
   * If removed and it was primary, sets primary to the last remaining item or null.
   */
  public toggle(id: string): this {
    if (this.selectedIds.has(id)) {
      this.selectedIds.delete(id);
      if (this.primaryId === id) {
        const remaining = Array.from(this.selectedIds);
        this.primaryId = remaining.length > 0 ? remaining[remaining.length - 1] : null;
      }
    } else {
      this.selectedIds.add(id);
      this.primaryId = id;
    }
    return this;
  }

  /**
   * Adds one or more IDs to the selection without clearing existing items.
   */
  public add(idOrIds: string | readonly string[]): this {
    const ids = Array.isArray(idOrIds) ? idOrIds : [idOrIds as string];
    for (const id of ids) {
      this.selectedIds.add(id);
      this.primaryId = id;
    }
    return this;
  }

  /**
   * Removes one or more IDs from the selection.
   */
  public remove(idOrIds: string | readonly string[]): this {
    const ids = Array.isArray(idOrIds) ? idOrIds : [idOrIds as string];
    let removedPrimary = false;

    for (const id of ids) {
      if (this.selectedIds.delete(id)) {
        if (this.primaryId === id) {
          removedPrimary = true;
        }
      }
    }

    if (removedPrimary) {
      const remaining = Array.from(this.selectedIds);
      this.primaryId = remaining.length > 0 ? remaining[remaining.length - 1] : null;
    }

    return this;
  }

  /**
   * Sets exact selection list and optional primary item.
   */
  public setSelection(ids: readonly string[], primaryId?: string | null): this {
    this.selectedIds = new Set(ids);

    if (primaryId !== undefined) {
      if (primaryId !== null && this.selectedIds.has(primaryId)) {
        this.primaryId = primaryId;
      } else {
        this.primaryId = null;
      }
    } else {
      const arr = Array.from(this.selectedIds);
      this.primaryId = arr.length > 0 ? arr[arr.length - 1] : null;
    }

    return this;
  }

  /**
   * Selects all given IDs.
   */
  public selectAll(allIds: readonly string[]): this {
    this.selectedIds = new Set(allIds);
    if (this.primaryId === null || !this.selectedIds.has(this.primaryId)) {
      this.primaryId = allIds.length > 0 ? allIds[0] : null;
    }
    return this;
  }

  /**
   * Clears selection completely.
   */
  public clear(): this {
    this.selectedIds.clear();
    this.primaryId = null;
    return this;
  }

  /**
   * Queries which items intersect or are contained within a marquee box.
   */
  public queryMarquee(
    items: readonly IdentifiedRect[],
    marquee: Rect,
    mode: MarqueeQueryMode = 'intersect'
  ): string[] {
    const matchingIds: string[] = [];

    for (const item of items) {
      const matches =
        mode === 'contain'
          ? rectContains(marquee, item)
          : rectIntersects(marquee, item);

      if (matches) {
        matchingIds.push(item.id);
      }
    }

    return matchingIds;
  }

  /**
   * Performs marquee selection over items.
   * If additive is true (e.g. Shift held), adds to existing selection; otherwise replaces.
   */
  public selectMarquee(
    items: readonly IdentifiedRect[],
    marquee: Rect,
    additive = false,
    mode: MarqueeQueryMode = 'intersect'
  ): string[] {
    const matchingIds = this.queryMarquee(items, marquee, mode);

    if (additive) {
      for (const id of matchingIds) {
        this.selectedIds.add(id);
      }
      if (matchingIds.length > 0) {
        this.primaryId = matchingIds[matchingIds.length - 1];
      }
    } else {
      this.setSelection(matchingIds);
    }

    return matchingIds;
  }

  public toState(): SelectionSerializedState {
    return {
      selectedIds: Array.from(this.selectedIds),
      primaryId: this.primaryId,
    };
  }

  public fromState(state: SelectionSerializedState): this {
    return this.setSelection(state.selectedIds, state.primaryId);
  }

  public clone(): StudioSelection {
    return new StudioSelection(Array.from(this.selectedIds), this.primaryId);
  }
}

/**
 * Factory helper for creating StudioSelection instances.
 */
export function createStudioSelection(
  initialIds?: readonly string[],
  initialPrimary?: string | null
): StudioSelection {
  return new StudioSelection(initialIds, initialPrimary);
}
