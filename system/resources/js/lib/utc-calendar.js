export function utcDayKey(value) {
    if (!value) return null;
    const date = new Date(value.length === 10 ? `${value}T00:00:00Z` : value);
    if (Number.isNaN(date.getTime())) return null;

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}

export function formatUtcDate(value, dateStyle = 'medium') {
    const date = value?.length === 10 ? new Date(`${value}T00:00:00Z`) : new Date(value);
    if (!value || Number.isNaN(date.getTime())) return 'Not set';

    return `${new Intl.DateTimeFormat(undefined, { dateStyle, timeZone: 'UTC' }).format(date)} UTC`;
}

export function formatUtcInstant(value) {
    const date = new Date(value);
    if (!value || Number.isNaN(date.getTime())) return 'Not set';

    return `${new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
        timeZone: 'UTC',
    }).format(date)} UTC`;
}

export function addUtcDays(value, days) {
    const date = new Date(value);
    if (!value || Number.isNaN(date.getTime()) || !Number.isInteger(days) || days < 1) return null;

    date.setUTCDate(date.getUTCDate() + days);
    if (Number.isNaN(date.getTime()) || date.getUTCFullYear() > 9999) return null;

    return date.toISOString();
}

export function addUtcDateDays(value, days) {
    if (!value || value.length !== 10) return null;

    const date = new Date(`${value}T00:00:00Z`);
    if (Number.isNaN(date.getTime()) || !Number.isInteger(days) || days < 1) return null;

    date.setUTCDate(date.getUTCDate() + days);
    if (Number.isNaN(date.getTime()) || date.getUTCFullYear() > 9999) return null;

    return utcDayKey(date.toISOString());
}

export function utcCalendarWeeks(value) {
    if (!value) return [];
    const [year, month] = value.split('-').map(Number);
    if (!year || !month || month > 12) return [];

    const firstDay = new Date(`${value}-01T00:00:00Z`);
    if (Number.isNaN(firstDay.getTime())) return [];
    const firstOffset = (firstDay.getUTCDay() + 6) % 7;
    const leapYear = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    const daysInMonth = [
        31, leapYear ? 29 : 28, 31, 30, 31, 30,
        31, 31, 30, 31, 30, 31,
    ][month - 1];
    const totalCells = Math.ceil((firstOffset + daysInMonth) / 7) * 7;
    const cells = Array.from({ length: totalCells }, (_, index) => {
        const day = index - firstOffset + 1;
        if (day < 1 || day > daysInMonth) {
            return { key: `blank-${value}-${index}`, date: null };
        }

        const date = `${value}-${String(day).padStart(2, '0')}`;
        return { key: date, date };
    });

    return Array.from({ length: totalCells / 7 }, (_, index) => cells.slice(index * 7, index * 7 + 7));
}

export function shiftedUtcMonth(value, offset) {
    if (!value || !Number.isInteger(offset)) return value;
    const [year, month] = value.split('-').map(Number);
    if (!year || !month) return value;

    const monthIndex = month - 1 + offset;
    const nextYear = year + Math.floor(monthIndex / 12);
    const nextMonth = ((monthIndex % 12) + 12) % 12 + 1;

    return `${nextYear}-${String(nextMonth).padStart(2, '0')}`;
}
