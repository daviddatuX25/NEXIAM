export interface StudioHistoryEntry<T> {
  state: T;
  label: string;
  coalesceKey?: string;
}

export interface StudioHistoryStatus {
  canUndo: boolean;
  canRedo: boolean;
  undoLabel: string | null;
  redoLabel: string | null;
}

export interface StudioHistory<T> {
  present(): T;
  record(next: T, label: string, options?: { coalesceKey?: string; windowMs?: number }): boolean;
  undo(): T | null;
  redo(): T | null;
  reset(state: T): void;
  clear(): void;
  status(): StudioHistoryStatus;
}

function clone<T>(value: T): T {
  return structuredClone(value);
}

function equal<T>(left: T, right: T): boolean {
  return JSON.stringify(left) === JSON.stringify(right);
}

/**
 * Local, bounded undo/redo history for JSON-shaped editor state.
 * The server remains the persistence and concurrency authority.
 */
export function createStudioHistory<T>(initial: T, maxEntries = 100): StudioHistory<T> {
  const limit = Math.max(1, Math.floor(maxEntries));
  let current = clone(initial);
  let past: StudioHistoryEntry<T>[] = [];
  let future: StudioHistoryEntry<T>[] = [];
  let lastCoalesceKey: string | undefined;
  let lastCoalesceAt = 0;

  function resetCoalescing(): void {
    lastCoalesceKey = undefined;
    lastCoalesceAt = 0;
  }

  return {
    present(): T {
      return clone(current);
    },

    record(next: T, label: string, options = {}): boolean {
      const nextState = clone(next);
      if (equal(current, nextState)) return false;

      const now = Date.now();
      const windowMs = options.windowMs ?? 450;
      const canCoalesce = Boolean(
        options.coalesceKey
        && options.coalesceKey === lastCoalesceKey
        && now - lastCoalesceAt <= windowMs
        && past.length > 0,
      );

      if (!canCoalesce) {
        past.push({
          state: clone(current),
          label,
          coalesceKey: options.coalesceKey,
        });
        if (past.length > limit) past = past.slice(-limit);
      } else {
        past[past.length - 1].label = label;
      }

      current = nextState;
      future = [];
      lastCoalesceKey = options.coalesceKey;
      lastCoalesceAt = now;
      return true;
    },

    undo(): T | null {
      const entry = past.pop();
      if (!entry) return null;
      future.unshift({ state: clone(current), label: entry.label });
      current = clone(entry.state);
      resetCoalescing();
      return clone(current);
    },

    redo(): T | null {
      const entry = future.shift();
      if (!entry) return null;
      past.push({ state: clone(current), label: entry.label });
      if (past.length > limit) past = past.slice(-limit);
      current = clone(entry.state);
      resetCoalescing();
      return clone(current);
    },

    reset(state: T): void {
      current = clone(state);
      past = [];
      future = [];
      resetCoalescing();
    },

    clear(): void {
      past = [];
      future = [];
      resetCoalescing();
    },

    status(): StudioHistoryStatus {
      return {
        canUndo: past.length > 0,
        canRedo: future.length > 0,
        undoLabel: past.at(-1)?.label ?? null,
        redoLabel: future[0]?.label ?? null,
      };
    },
  };
}
