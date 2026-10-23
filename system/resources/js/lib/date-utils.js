/**
 * Centralized human date and time formatting utilities.
 *
 * Formats raw database strings (ISO-8601, Y-m-d, HH:mm:ss) into clean,
 * scan-friendly display strings with 12-hour AM/PM and readable months.
 */

/**
 * Parse dates safely in local timezone without UTC offset shifts.
 * @param {string|Date|null|undefined} value
 * @returns {Date|null}
 */
export function parseDate(value) {
  if (!value) return null;
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
  if (typeof value !== 'string') return null;

  const ymd = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (ymd) {
    const year = Number(ymd[1]);
    const month = Number(ymd[2]);
    const day = Number(ymd[3]);
    if (year < 1 || month < 1 || month > 12 || day < 1 || day > 31) return null;

    const date = new Date(0);
    date.setHours(0, 0, 0, 0);
    date.setFullYear(year, month - 1, day);
    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

/** Format date string or object into a readable calendar date. */
export function formatDate(value, length = 'short') {
  const date = parseDate(value);
  if (!date) return '—';
  return date.toLocaleDateString('en-US', {
    month: length === 'short' ? 'short' : 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

/** Format a calendar-day anchor (Today, Tomorrow, Yesterday, or a short date). */
export function formatHumanDate(value) {
  const date = parseDate(value);
  if (!date) return '—';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(date);
  target.setHours(0, 0, 0, 0);
  const diffDays = Math.round((target.getTime() - today.getTime()) / 86400000);
  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Tomorrow';
  if (diffDays === -1) return 'Yesterday';
  if (diffDays > 1 && diffDays <= 6) {
    return target.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
  }
  return formatDate(value, 'short');
}

/** Format 24-hour time string into a 12-hour display. */
export function formatTime(timeStr) {
  if (!timeStr) return '';
  const parts = String(timeStr).split(':');
  if (parts.length < 2) return timeStr;
  let hour = Number.parseInt(parts[0], 10);
  const minute = Number.parseInt(parts[1], 10);
  if (!Number.isInteger(hour) || hour < 0 || hour > 23 || !Number.isInteger(minute) || minute < 0 || minute > 59) return '';
  const ampm = hour >= 12 ? 'PM' : 'AM';
  hour %= 12;
  if (hour === 0) hour = 12;
  return `${hour}:${String(minute).padStart(2, '0')} ${ampm}`;
}

/** Format a time range. */
export function formatTimeRange(start, end) {
  const startFmt = formatTime(start);
  const endFmt = formatTime(end);
  if (startFmt && endFmt) return `${startFmt} – ${endFmt}`;
  return startFmt || endFmt || '—';
}

/** Format a complete schedule. */
export function formatSchedule(date, start, end) {
  const dateFmt = formatDate(date);
  const timeRange = formatTimeRange(start, end);
  if (dateFmt === '—' && timeRange === '—') return '—';
  if (timeRange === '—') return dateFmt;
  if (dateFmt === '—') return timeRange;
  return `${dateFmt} · ${timeRange}`;
}

/** Format a human session schedule. */
export function formatHumanSchedule(date, start, end, includeEnd = false) {
  const day = formatHumanDate(date);
  const time = includeEnd ? formatTimeRange(start, end) : formatTime(start);
  if (day === '—' && !time) return '—';
  if (!time) return day;
  return `${day}, ${time}`;
}

/** Format relative time. */
export function formatRelative(value) {
  const date = parseDate(value);
  if (!date) return '—';
  const diffMs = date.getTime() - Date.now();
  const absDiff = Math.abs(diffMs);
  const diffMin = Math.round(absDiff / 60000);
  const diffHour = Math.round(absDiff / 3600000);
  const diffDay = Math.round(absDiff / 86400000);
  if (diffMs > 0) {
    if (diffMin < 60) return `in ${diffMin}m`;
    if (diffHour < 24) return `in ${diffHour}h`;
    return `in ${diffDay}d`;
  }
  if (diffMin < 1) return 'just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  if (diffHour < 24) return `${diffHour}h ago`;
  return `${diffDay}d ago`;
}

/** Format an ISO calendar date as a birthdate without timezone conversion. */
export function formatBirthdateDisplay(isoDate, style = 'medium') {
  if (typeof isoDate !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) return '—';
  const date = parseDate(isoDate);
  if (!date) return '—';
  return date.toLocaleDateString('en-US', {
    month: style === 'medium' ? 'short' : 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/** Format a date/time instant, preserving date-only calendar values. */
export function formatDateTime(instant) {
  if (!instant) return '—';
  if (typeof instant === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(instant)) return parseDate(instant) ? formatDate(instant) : '—';
  const date = parseDate(instant);
  return date ? date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';
}

/** Format an instant as local 12-hour time. */
export function formatInstantTime(instant) {
  if (!instant) return '—';
  const date = parseDate(instant);
  return date ? date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '—';
}

/**
 * Format notification timestamp e.g. "Aug. 22 · 10:03 AM".
 * Minimal date and time with standard abbreviation and middle dot.
 * @param {string|Date|null|undefined} value
 * @returns {string}
 */
export function formatNotificationTimestamp(value) {
  if (!value) return '';
  const date = parseDate(value);
  if (!date) return '';

  const monthNames = [
    'Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'Jun.',
    'Jul.', 'Aug.', 'Sep.', 'Oct.', 'Nov.', 'Dec.'
  ];
  const month = monthNames[date.getMonth()];
  const day = date.getDate();
  const year = date.getFullYear();
  const currentYear = new Date().getFullYear();

  let hours = date.getHours();
  const minutes = date.getMinutes().toString().padStart(2, '0');
  const ampm = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12;
  hours = hours ? hours : 12;
  const time = `${hours}:${minutes} ${ampm}`;

  const datePart = year === currentYear ? `${month} ${day}` : `${month} ${day}, ${year}`;
  return `${datePart} · ${time}`;
}

const ENUM_ACRONYMS = ['OMR', 'PSA', 'TOR', 'SF9', 'GWA', 'BSIT', 'BSN', 'QR', 'ID', 'PH', 'TA', 'HR'];

/** Humanize machine enum values while retaining common institutional acronyms. */
export function formatEnumLabel(value, overrides = {}) {
  if (value == null || String(value).trim() === '') return '—';
  if (Object.prototype.hasOwnProperty.call(overrides, value)) return overrides[value];
  const normalized = String(value).replace(/[_-]+/g, ' ').trim();
  if (!normalized) return '—';
  const acronymPattern = new RegExp(`\\b(${ENUM_ACRONYMS.join('|')})\\b`, 'gi');
  return normalized
    .replace(acronymPattern, (word) => ENUM_ACRONYMS.find((acronym) => acronym.toLowerCase() === word.toLowerCase()))
    .replace(/^[a-z]/i, (letter) => letter.toUpperCase());
}
