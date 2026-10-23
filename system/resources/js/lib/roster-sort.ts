export type SortDirection = 'asc' | 'desc';

const collator = new Intl.Collator(undefined, {
    numeric: true,
    sensitivity: 'base',
});

/**
 * Pure client-side sorter for session roster and candidate rows.
 * Preserves input order when sortKey is empty.
 */
export function sortRosterRows<T extends Record<string, unknown>>(
    rows: readonly T[],
    sortKey: string,
    sortDir: SortDirection = 'asc',
): T[] {
    if (!sortKey) {
        return [...rows];
    }

    const factor = sortDir === 'desc' ? -1 : 1;

    return [...rows].sort((a, b) => {
        const valA = formatSortableValue(a, sortKey);
        const valB = formatSortableValue(b, sortKey);

        if (valA === '' && valB !== '') return 1;
        if (valA !== '' && valB === '') return -1;

        const cmp = collator.compare(valA, valB);
        if (cmp !== 0) {
            return cmp * factor;
        }

        const idA = Number(a.id ?? a.attempt_id ?? 0);
        const idB = Number(b.id ?? b.attempt_id ?? 0);
        return (idA - idB) * factor;
    });
}

function formatSortableValue(row: Record<string, unknown>, key: string): string {
    if (key === 'attendance') {
        const att = String(row.attendance ?? '');
        const sub = Boolean(row.submitted);
        if (sub) return '1_submitted';
        if (att === 'present') return '2_present';
        if (att === 'absent') return '3_absent';
        if (att === 'void') return '4_void';
        return '5_unrecorded';
    }
    if (key === 'reference_number' || key === 'reference_code') {
        return String(row.reference_number ?? row.reference_code ?? '').trim();
    }
    return String(row[key] ?? '').trim();
}

export function nextColumnSort(
    currentSort: string,
    currentDir: SortDirection,
    clickedKey: string,
): { sort: string; dir: SortDirection } {
    if (currentSort !== clickedKey) {
        return { sort: clickedKey, dir: 'asc' };
    }
    return {
        sort: clickedKey,
        dir: currentDir === 'asc' ? 'desc' : 'asc',
    };
}

export function ariaSortDirection(
    currentSort: string,
    currentDir: SortDirection,
    columnKey: string,
): 'none' | 'ascending' | 'descending' {
    if (currentSort !== columnKey) {
        return 'none';
    }
    return currentDir === 'asc' ? 'ascending' : 'descending';
}
