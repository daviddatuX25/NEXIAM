const ISO_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const DISPLAY_DATE_PATTERN = /^(\d{2})-(\d{2})-(\d{4})$/;
const MONTH_LENGTHS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

export function parseMmDdYyyyBirthdate(value: string): string | null {
    const match = DISPLAY_DATE_PATTERN.exec(value);

    if (!match) {
        return null;
    }

    const [, monthText, dayText, yearText] = match;
    const month = Number(monthText);
    const day = Number(dayText);
    const year = Number(yearText);

    if (year < 1 || month < 1 || month > 12) {
        return null;
    }

    const daysInMonth =
        month === 2 && isLeapYear(year) ? 29 : (MONTH_LENGTHS[month - 1] ?? 0);

    if (day < 1 || day > daysInMonth) {
        return null;
    }

    return `${yearText}-${monthText}-${dayText}`;
}

export function formatIsoBirthdate(value: string): string | null {
    const match = ISO_DATE_PATTERN.exec(value);

    if (!match) {
        return null;
    }

    const [, year, month, day] = match;
    const displayValue = `${month}-${day}-${year}`;

    return parseMmDdYyyyBirthdate(displayValue) ? displayValue : null;
}

function isLeapYear(year: number): boolean {
    return year % 400 === 0 || (year % 4 === 0 && year % 100 !== 0);
}
