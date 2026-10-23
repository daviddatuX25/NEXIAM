/**
 * Bounded undo/redo history engine with continuous gesture coalescing
 * and gesture transaction support for document studio editors.
 */

import type {
  GestureTransaction,
  HistoryEntry,
  HistoryOptions,
  HistoryStatus,
} from './types.js';

function defaultClone<T>(value: T): T {
  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(value);
    } catch {
      // Fallback for non-cloneable objects
    }
  }
  return JSON.parse(JSON.stringify(value)) as T;
}

function defaultEqual<T>(a: T, b: T): boolean {
  if (a === b) return true;
  if (a === null || b === null || typeof a !== 'object' || typeof b !== 'object') {
    return false;
  }
  return JSON.stringify(a) === JSON.stringify(b);
}

export class StudioHistory<T> {
  private readonly maxDepth: number;
  private readonly coalesceWindowMs: number;
  private readonly cloneFn: (val: T) => T;
  private readonly equalityFn: (a: T, b: T) => boolean;

  private current: T;
  private cleanBaseline: T;
  private past: HistoryEntry<T>[] = [];
  private future: HistoryEntry<T>[] = [];

  private lastCoalesceKey: string | undefined;
  private lastCoalesceAt = 0;

  private activeGesture: GestureTransaction<T> | null = null;

  constructor(initial: T, options: HistoryOptions<T> = {}) {
    this.maxDepth = Math.max(1, Math.floor(options.maxDepth ?? 100));
    this.coalesceWindowMs = Math.max(0, options.coalesceWindowMs ?? 400);
    this.cloneFn = options.cloneFn ?? defaultClone;
    this.equalityFn = options.equalityFn ?? defaultEqual;

    this.current = this.cloneFn(initial);
    this.cleanBaseline = this.cloneFn(initial);
  }

  public get present(): T {
    return this.cloneFn(this.current);
  }

  public getPresent(): T {
    return this.cloneFn(this.current);
  }

  private resetCoalescing(): void {
    this.lastCoalesceKey = undefined;
    this.lastCoalesceAt = 0;
  }

  /**
   * Records a new state in the history stack.
   * If coalesceKey matches within coalesceWindowMs, updates present without adding a new history entry.
   */
  public record(
    next: T,
    label: string,
    options: { coalesceKey?: string; windowMs?: number } = {}
  ): boolean {
    const nextState = this.cloneFn(next);
    if (this.equalityFn(this.current, nextState)) {
      return false;
    }

    const now = Date.now();
    const windowMs = options.windowMs ?? this.coalesceWindowMs;
    const canCoalesce = Boolean(
      options.coalesceKey &&
        options.coalesceKey === this.lastCoalesceKey &&
        now - this.lastCoalesceAt <= windowMs &&
        this.past.length > 0
    );

    if (!canCoalesce) {
      this.past.push({
        state: this.cloneFn(this.current),
        label,
        timestamp: now,
        coalesceKey: options.coalesceKey,
      });

      if (this.past.length > this.maxDepth) {
        this.past = this.past.slice(-this.maxDepth);
      }
    } else {
      // Update label of current coalesced block
      const lastEntry = this.past[this.past.length - 1];
      this.past[this.past.length - 1] = {
        ...lastEntry,
        label,
      };
    }

    this.current = nextState;
    this.future = [];
    this.lastCoalesceKey = options.coalesceKey;
    this.lastCoalesceAt = now;
    return true;
  }

  /**
   * Convenience alias matching push(next, coalesceKey, windowMs, label).
   */
  public push(
    next: T,
    coalesceKey?: string,
    windowMs?: number,
    label = 'Change'
  ): boolean {
    return this.record(next, label, { coalesceKey, windowMs });
  }

  /**
   * Begins a continuous gesture transaction (e.g. pointer drag, resize, marquee).
   * Intermediate updates can be applied via updateGesture() and reverted cleanly with cancelGesture().
   */
  public beginGesture(label: string, coalesceKey?: string): void {
    this.activeGesture = {
      label,
      coalesceKey,
      initialState: this.cloneFn(this.current),
      isCommitted: false,
      isCancelled: false,
    };
  }

  /**
   * Updates state during an active gesture without recording history entries.
   */
  public updateGesture(next: T): void {
    this.current = this.cloneFn(next);
  }

  /**
   * Commits the active gesture transaction. If state changed from gesture start,
   * records a single history entry with the gesture label.
   */
  public commitGesture(): boolean {
    if (!this.activeGesture || this.activeGesture.isCancelled) {
      this.activeGesture = null;
      return false;
    }

    const { label, coalesceKey, initialState } = this.activeGesture;
    this.activeGesture = null;

    if (this.equalityFn(initialState, this.current)) {
      return false;
    }

    const now = Date.now();
    this.past.push({
      state: initialState,
      label,
      timestamp: now,
      coalesceKey,
    });

    if (this.past.length > this.maxDepth) {
      this.past = this.past.slice(-this.maxDepth);
    }

    this.future = [];
    this.lastCoalesceKey = coalesceKey;
    this.lastCoalesceAt = now;
    return true;
  }

  /**
   * Cancels the active gesture and restores state to the initial gesture state.
   */
  public cancelGesture(): T {
    if (!this.activeGesture) {
      return this.present;
    }

    this.current = this.cloneFn(this.activeGesture.initialState);
    this.activeGesture = null;
    return this.present;
  }

  public isInGesture(): boolean {
    return this.activeGesture !== null;
  }

  public getGestureTransaction(): GestureTransaction<T> | null {
    return this.activeGesture ? { ...this.activeGesture } : null;
  }

  /**
   * Undoes the last recorded history step.
   */
  public undo(): T | null {
    if (this.past.length === 0) {
      return null;
    }

    const entry = this.past.pop()!;
    this.future.unshift({
      state: this.cloneFn(this.current),
      label: entry.label,
      timestamp: Date.now(),
    });

    this.current = this.cloneFn(entry.state);
    this.resetCoalescing();
    return this.present;
  }

  /**
   * Redoes the next available future step.
   */
  public redo(): T | null {
    if (this.future.length === 0) {
      return null;
    }

    const entry = this.future.shift()!;
    this.past.push({
      state: this.cloneFn(this.current),
      label: entry.label,
      timestamp: Date.now(),
    });

    if (this.past.length > this.maxDepth) {
      this.past = this.past.slice(-this.maxDepth);
    }

    this.current = this.cloneFn(entry.state);
    this.resetCoalescing();
    return this.present;
  }

  public canUndo(): boolean {
    return this.past.length > 0;
  }

  public canRedo(): boolean {
    return this.future.length > 0;
  }

  /**
   * Resets history completely and replaces current present with state.
   * Updates clean baseline for isDirty.
   */
  public reset(state: T): void {
    this.current = this.cloneFn(state);
    this.cleanBaseline = this.cloneFn(state);
    this.past = [];
    this.future = [];
    this.activeGesture = null;
    this.resetCoalescing();
  }

  /**
   * Clears past and future undo stacks while preserving current present.
   */
  public clear(): void {
    this.past = [];
    this.future = [];
    this.activeGesture = null;
    this.resetCoalescing();
  }

  /**
   * Marks current present as clean baseline for isDirty check.
   */
  public markClean(): void {
    this.cleanBaseline = this.cloneFn(this.current);
  }

  /**
   * Evaluates if current present differs from clean baseline or provided initial state.
   */
  public isDirty(initial?: T): boolean {
    const baseline = initial !== undefined ? initial : this.cleanBaseline;
    return !this.equalityFn(this.current, baseline);
  }

  /**
   * Returns labeled status summary for editor toolbars.
   */
  public status(): HistoryStatus {
    const undoEntry = this.past[this.past.length - 1];
    const redoEntry = this.future[0];

    return {
      canUndo: this.past.length > 0,
      canRedo: this.future.length > 0,
      undoLabel: undoEntry ? undoEntry.label : null,
      redoLabel: redoEntry ? redoEntry.label : null,
      pastCount: this.past.length,
      futureCount: this.future.length,
      isDirty: this.isDirty(),
    };
  }
}

/**
 * Factory helper for creating StudioHistory instances.
 */
export function createStudioHistory<T>(
  initial: T,
  options?: HistoryOptions<T> | number
): StudioHistory<T> {
  const normalizedOptions: HistoryOptions<T> =
    typeof options === 'number' ? { maxDepth: options } : (options ?? {});
  return new StudioHistory<T>(initial, normalizedOptions);
}
