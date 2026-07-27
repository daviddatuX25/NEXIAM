/**
 * Auto-filter utilities for Svelte 5 $effect() blocks.
 *
 * Usage in a component:
 *   const searchWatch = createDebouncedWatch(() => applyFilters());
 *   const filterWatch = createImmediateWatch(() => applyFilters());
 *
 *   $effect(() => { filterSearch; searchWatch(); });
 *   $effect(() => { filterStatus; filterSeason; filterWatch(); });
 */

/**
 * Creates a debounced callback that skips the first invocation.
 * Use inside $effect() for text input auto-search.
 *
 * @param {Function} fn - The function to call after debounce
 * @param {number} ms - Debounce delay in milliseconds (default: 300)
 */
export function createDebouncedWatch(fn, ms = 300) {
  let timer = null;
  let first = true;

  return function debouncedWatch() {
    if (first) { first = false; return; }
    clearTimeout(timer);
    timer = setTimeout(fn, ms);
  };
}

/**
 * Creates an immediate callback that skips the first invocation.
 * Use inside $effect() for dropdown/date filter changes.
 *
 * @param {Function} fn - The function to call immediately
 */
export function createImmediateWatch(fn) {
  let first = true;

  return function immediateWatch() {
    if (first) { first = false; return; }
    fn();
  };
}
