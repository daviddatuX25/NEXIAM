/**
 * Flatten server-authored navigation groups for single-active-link resolution.
 *
 * @param {Array<{ items?: Array<object> | Record<string, object> }>} sections
 * @returns {Array<object>}
 */
export function flattenStaffNavSections(sections) {
  if (!Array.isArray(sections)) {
    return [];
  }

  return sections.flatMap((section) => (
    Array.isArray(section?.items) ? section.items : Object.values(section?.items ?? {})
  ));
}

const STAFF_NAV_STORAGE_PREFIX = 'nexiam.staff.navigation:v1:';

/**
 * Keep section preferences scoped to both the actor and this login's existing
 * staff inactivity generation. A new account or login therefore starts fresh.
 *
 * @param {number|string|null|undefined} actorId
 * @param {string|null|undefined} generation
 * @returns {string|null}
 */
export function staffNavStorageKey(actorId, generation) {
  if ((typeof actorId !== 'number' && typeof actorId !== 'string') || String(actorId) === '' || typeof generation !== 'string' || generation === '') {
    return null;
  }

  return `${STAFF_NAV_STORAGE_PREFIX}${encodeURIComponent(String(actorId))}:${encodeURIComponent(generation)}`;
}

/**
 * @param {Array<{ key?: string, collapsible?: boolean, default_collapsed?: boolean }>} sections
 * @param {number|string|null|undefined} actorId
 * @param {string|null|undefined} generation
 * @param {Storage|undefined} storage
 * @returns {{ key: string|null, expanded: Record<string, boolean> }}
 */
export function loadStaffNavSectionState(sections, actorId, generation, storage) {
  const key = staffNavStorageKey(actorId, generation);
  const expanded = Object.fromEntries(
    sections
      .filter((section) => section?.collapsible === true && typeof section.key === 'string')
      .map((section) => [section.key, section.default_collapsed !== true]),
  );

  if (key !== null && storage) {
    try {
      const saved = JSON.parse(storage.getItem(key) ?? 'null');
      if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
        for (const section of sections) {
          if (section?.collapsible === true && typeof section.key === 'string' && typeof saved[section.key] === 'boolean') {
            expanded[section.key] = saved[section.key];
          }
        }
      }
    } catch {
      // Corrupt or unavailable preference storage falls back to server defaults.
    }
  }

  return { key, expanded };
}

/**
 * @param {{ key: string|null, expanded: Record<string, boolean> }} state
 * @param {string} sectionKey
 * @param {boolean} isExpanded
 * @param {Storage|undefined} storage
 * @returns {{ key: string|null, expanded: Record<string, boolean> }}
 */
export function setStaffNavSectionExpanded(state, sectionKey, isExpanded, storage) {
  const next = { key: state.key, expanded: { ...state.expanded, [sectionKey]: isExpanded } };
  if (state.key !== null && storage) {
    try {
      storage.setItem(state.key, JSON.stringify(next.expanded));
    } catch {
      // The choice still applies until this layout is recreated.
    }
  }

  return next;
}

/**
 * @param {Array<{ key?: string, collapsible?: boolean }>} sections
 * @param {Array<object>} items
 * @param {string} rawUrl
 * @returns {string|null}
 */
export function activeStaffNavSectionKey(sections, items, rawUrl) {
  for (const section of sections) {
    if (section?.collapsible === true && typeof section.key === 'string'
      && section.items?.some((item) => isStaffNavActive(item, items, rawUrl))) {
      return section.key;
    }
  }

  return null;
}

/**
 * Server-authored staff nav active resolution.
 * Exactly one item wins: exact matches beat prefixes; longest prefix wins among prefixes.
 *
 * @param {{ href: string, match?: 'exact' | 'prefix' }} item
 * @param {Array<{ href: string, match?: 'exact' | 'prefix' }>} items
 * @param {string} rawUrl
 */
export function isStaffNavActive(item, items, rawUrl) {
  const url = normalizePath(rawUrl);
  let bestHref = null;
  let bestScore = -1;

  for (const candidate of items) {
    const score = scoreNavItem(candidate, url);
    if (score > bestScore) {
      bestScore = score;
      bestHref = candidate.href;
    }
  }

  return bestScore >= 0 && bestHref === item.href;
}

/**
 * @param {{ href: string, match?: 'exact' | 'prefix' }} item
 * @param {string} url
 */
const EXAM_SESSION_MONITORING_PATH = /^\/exams\/sessions\/[^/]+\/monitoring(?:\/|$)/;

function scoreNavItem(item, url) {
  const href = normalizePath(item.href ?? '');
  const mode = item.match === 'exact' ? 'exact' : 'prefix';

  if (EXAM_SESSION_MONITORING_PATH.test(url)) {
    if (href === '/exams/monitoring') {
      return 100_000 + href.length;
    }
    if (href === '/exams/sessions') {
      return -1;
    }
  }

  if (mode === 'exact') {
    return url === href ? 1_000_000 : -1;
  }

  if (url === href) {
    return 100_000 + href.length;
  }

  if (href !== '/' && url.startsWith(`${href}/`)) {
    return href.length;
  }

  return -1;
}

/** @param {string} path */
function normalizePath(path) {
  const cleaned = (path || '/').split('?')[0].split('#')[0];
  if (cleaned.length > 1 && cleaned.endsWith('/')) {
    return cleaned.slice(0, -1);
  }
  return cleaned || '/';
}
