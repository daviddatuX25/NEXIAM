/**
 * Legacy auto-filter helpers (presentational / client UX only).
 * Ported from legacy `lib/auto-filter` for gallery demos and future list pages.
 * Server remains authority for filtered results.
 */

/**
 * @param {() => void} fn
 * @param {number} [ms]
 */
export function createDebouncedWatch(fn, ms = 300) {
  let timer = null;
  return () => {
    if (timer) {
      clearTimeout(timer);
    }
    timer = setTimeout(() => {
      timer = null;
      fn();
    }, ms);
  };
}

/**
 * @param {() => void} fn
 */
export function createImmediateWatch(fn) {
  return () => {
    fn();
  };
}
