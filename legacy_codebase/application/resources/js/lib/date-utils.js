/**
 * Centralized date formatting utilities.
 *
 * Standardized formats:
 *   'long'     → "May 17, 2026"
 *   'short'    → "May 17, 2026"
 *   'datetime' → "May 17, 2026 at 3:30 PM"
 *   'relative' → "just now" / "5m ago" / "2h ago" / falls back to short
 *   'time'     → "3:30 PM"
 */

/**
 * Parse various date formats into a reliable Date object.
 * Handles: Y-m-d strings, ISO-8601, Date objects, null/undefined.
 *
 * @param {string|Date|null|undefined} value
 * @returns {Date|null}
 */
function parseDate(value) {
    if (!value) return null;
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
    if (typeof value !== 'string') return null;

    // Plain Y-m-d (or Y-M-D) — construct in local timezone to avoid UTC shift
    const ymd = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (ymd) {
        return new Date(Number(ymd[1]), Number(ymd[2]) - 1, Number(ymd[3]));
    }

    // ISO-8601 with possible T separator — delegate to native parser
    const d = new Date(value);
    return isNaN(d.getTime()) ? null : d;
}

/**
 * Format a date value into a human-friendly string.
 *
 * @param {string|Date|null|undefined} value  — raw date from backend
 * @param {'long'|'short'|'datetime'|'relative'|'time'} [format='long']
 * @returns {string} Formatted string, or '—' when invalid/missing
 */
export function formatDate(value, format = 'long') {
    const date = parseDate(value);
    if (!date) return '—';

    switch (format) {
        case 'long':
            // "May 17, 2026"
            return date.toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric',
            });

        case 'short':
            // "May 17, 2026"
            return date.toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
            });

        case 'datetime': {
            // "May 17, 2026 at 3:30 PM"
            const datePart = date.toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
            });
            const timePart = date.toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit', hour12: true,
            });
            return `${datePart} at ${timePart}`;
        }

        case 'relative': {
            const now = new Date();
            const diffMs = now - date;
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHr  = Math.floor(diffMin / 60);
            const diffDay = Math.floor(diffHr / 24);

            if (diffSec < 60)  return 'Just now';
            if (diffMin < 60)  return `${diffMin}m ago`;
            if (diffHr  < 24)  return `${diffHr}h ago`;
            if (diffDay === 1) return 'Yesterday';
            if (diffDay < 7)   return `${diffDay}d ago`;

            return date.toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
            });
        }

        case 'time':
            // "3:30 PM"
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit', hour12: true,
            });

        default:
            return date.toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric',
            });
    }
}

/**
 * Format a date+time value. Shorthand for formatDate(value, 'datetime').
 *
 * @param {string|Date|null|undefined} value
 * @returns {string}
 */
export function formatDateTime(value) {
    return formatDate(value, 'datetime');
}

/**
 * Format a date as relative time. Shorthand for formatDate(value, 'relative').
 *
 * @param {string|Date|null|undefined} value
 * @returns {string}
 */
export function formatRelativeDate(value) {
    return formatDate(value, 'relative');
}
