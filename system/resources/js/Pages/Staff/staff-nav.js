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
function scoreNavItem(item, url) {
  const href = normalizePath(item.href ?? '');
  const mode = item.match === 'exact' ? 'exact' : 'prefix';

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
