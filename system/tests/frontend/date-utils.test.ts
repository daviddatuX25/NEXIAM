import { describe, expect, it } from 'vitest';
import {
    formatBirthdateDisplay,
    formatDate,
    formatDateTime,
    formatEnumLabel,
    formatInstantTime,
    formatNotificationTimestamp,
    formatTime,
    parseDate,
} from '../../resources/js/lib/date-utils.js';

describe('date-utils', () => {
    it('validates local calendar components including years below 100', () => {
        expect(parseDate('2024-2-29')?.getFullYear()).toBe(2024);
        expect(parseDate('0001-01-01')?.getFullYear()).toBe(1);
        expect(parseDate('2023-02-31')).toBeNull();
        expect(parseDate('0000-01-01')).toBeNull();
        expect(parseDate(new Date(Number.NaN))).toBeNull();
        expect(parseDate('not a date')).toBeNull();
    });

    it('formats validated birthdates without UTC date shifting', () => {
        expect(formatBirthdateDisplay('2003-09-25')).toBe('Sep 25, 2003');
        expect(formatBirthdateDisplay('2003-02-31')).toBe('—');
        expect(formatBirthdateDisplay(null)).toBe('—');
    });

    it('formats timestamp instants and keeps date-only values as calendar dates', () => {
        expect(formatDateTime('2026-10-11')).toBe(formatDate('2026-10-11'));
        expect(formatDateTime('invalid')).toBe('—');
        expect(formatDateTime(null)).toBe('—');
        expect(formatDateTime(new Date('2026-10-11T11:25:00Z'))).toContain('Oct 11, 2026');
        expect(formatInstantTime('2026-10-11T11:25:00Z')).not.toBe('—');
        expect(formatInstantTime('invalid')).toBe('—');
    });

    it('formats notification timestamps e.g. Aug. 22 · 10:03 AM', () => {
        const testDate = new Date(2026, 7, 22, 10, 3); // August 22, 2026 10:03 AM
        expect(formatNotificationTimestamp(testDate)).toBe('Aug. 22 · 10:03 AM');
        expect(formatNotificationTimestamp('invalid')).toBe('');
        expect(formatNotificationTimestamp(null)).toBe('');
    });

    it('rejects invalid clock components and humanizes enums with overrides/acronyms', () => {
        expect(formatTime('24:00')).toBe('');
        expect(formatTime('10:60')).toBe('');
        expect(formatTime('09:05')).toBe('9:05 AM');
        expect(formatEnumLabel('needs_correction')).toBe('Needs correction');
        expect(formatEnumLabel('omr_review')).toBe('OMR review');
        expect(formatEnumLabel('needs_correction', { needs_correction: 'Needs review' })).toBe('Needs review');
        expect(formatEnumLabel('  ')).toBe('—');
    });
});
