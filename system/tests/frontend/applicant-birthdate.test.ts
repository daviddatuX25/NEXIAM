import { describe, expect, it } from 'vitest';
import {
    formatIsoBirthdate,
    parseMmDdYyyyBirthdate,
} from '../../resources/js/lib/applicant-birthdate';

describe('applicant birthdate format', () => {
    it('converts a valid MM-DD-YYYY date to the existing ISO request value', () => {
        expect(parseMmDdYyyyBirthdate('09-25-2003')).toBe('2003-09-25');
    });

    it('accepts leap days only in leap years', () => {
        expect(parseMmDdYyyyBirthdate('02-29-2000')).toBe('2000-02-29');
        expect(parseMmDdYyyyBirthdate('02-29-1900')).toBeNull();
    });

    it.each([
        '02-30-2004',
        '13-01-2004',
        '00-12-2004',
        '02-29-2001',
        '2-9-2001',
        '2001-02-09',
        '',
    ])('rejects invalid or non-MM-DD-YYYY input: %s', (value) => {
        expect(parseMmDdYyyyBirthdate(value)).toBeNull();
    });

    it('formats a valid ISO value for display and rejects invalid ISO dates', () => {
        expect(formatIsoBirthdate('2003-09-25')).toBe('09-25-2003');
        expect(formatIsoBirthdate('2001-02-29')).toBeNull();
        expect(formatIsoBirthdate('2003-9-25')).toBeNull();
    });
});
