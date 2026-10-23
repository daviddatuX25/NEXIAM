export type SuggestionCategory = 'schools' | 'shs' | 'programs';
export type SuggestionEntry = { label: string; aliases: string[] };
export type SuggestionList = { entries: SuggestionEntry[]; revision: number };
export type SuggestionDrafts = Record<
    SuggestionCategory,
    SuggestionList & { text: string; error: string; stale: boolean }
>;

export const suggestionCategories: SuggestionCategory[] = [
    'schools',
    'shs',
    'programs',
];
export const MAX_SUGGESTION_FILE_BYTES = 2 * 1024 * 1024;
export const MAX_SUGGESTION_ENTRIES = 5000;

export function createSuggestionDrafts(
    lists: Record<SuggestionCategory, SuggestionList>,
): SuggestionDrafts {
    return Object.fromEntries(
        suggestionCategories.map((category) => {
            const list = lists[category] ?? { entries: [], revision: 0 };
            return [
                category,
                {
                    entries: list.entries.map((entry) => ({
                        label: entry.label,
                        aliases: [...entry.aliases],
                    })),
                    revision: list.revision,
                    text: serializeSuggestionEntries(list.entries),
                    error: '',
                    stale: false,
                },
            ];
        }),
    ) as SuggestionDrafts;
}

function escapeDraftValue(value: string, alias = false): string {
    return value.replace(alias ? /[\\|,]/g : /[\\|]/g, '\\$&');
}

function splitDraftValue(value: string, separator: string): string[] {
    const parts = [''];
    for (let index = 0; index < value.length; index += 1) {
        const character = value[index];
        if (character === '\\' && /[\\|,]/.test(value[index + 1] ?? '')) {
            parts[parts.length - 1] += character + value[++index];
        } else if (character === separator) {
            parts.push('');
        } else {
            parts[parts.length - 1] += character;
        }
    }
    return parts;
}

function unescapeDraftValue(value: string): string {
    return value.replace(/\\([\\|,])/g, '$1').trim();
}

export function serializeSuggestionEntries(entries: SuggestionEntry[]): string {
    return entries
        .map(
            ({ label, aliases }) =>
                `${escapeDraftValue(label)}${aliases.length ? ` | ${aliases.map((value) => escapeDraftValue(value, true)).join(', ')}` : ''}`,
        )
        .join('\n');
}

export function parseSuggestionDraft(text: string): SuggestionEntry[] {
    return text.split(/\r?\n/).flatMap((line) => {
        const [rawLabel, ...rest] = splitDraftValue(line.trim(), '|');
        const label = unescapeDraftValue(rawLabel);
        if (!label) return [];
        const aliases = splitDraftValue(rest.join('|'), ',')
            .map(unescapeDraftValue)
            .filter(Boolean);
        return [{ label, aliases: [...new Set(aliases)] }];
    });
}

function parseCsvRecords(source: string): string[][] {
    const records: string[][] = [];
    let row: string[] = [];
    let field = '';
    let quoted = false;
    let closedQuote = false;
    for (let index = 0; index < source.length; index += 1) {
        const char = source[index];
        if (quoted) {
            if (char === '"' && source[index + 1] === '"') {
                field += '"';
                index += 1;
            } else if (char === '"') {
                quoted = false;
                closedQuote = true;
            } else {
                field += char;
            }
        } else if (char === '"') {
            if (field.length > 0 || closedQuote)
                throw new Error('Unexpected quote in a CSV field.');
            quoted = true;
        } else if (char === ',' || char === '\n' || char === '\r') {
            if (char === ',') {
                row.push(field);
                field = '';
                closedQuote = false;
            } else {
                if (char === '\r' && source[index + 1] === '\n') index += 1;
                row.push(field);
                if (row.some((cell) => cell.trim() !== '')) records.push(row);
                row = [];
                field = '';
                closedQuote = false;
            }
        } else {
            if (closedQuote)
                throw new Error('Unexpected text after a quoted CSV field.');
            field += char;
        }
    }
    if (quoted) throw new Error('A quoted CSV field is not closed.');
    row.push(field);
    if (row.some((cell) => cell.trim() !== '')) records.push(row);
    return records;
}

function normalizeHeader(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

export function parseSuggestionCsv(source: string): SuggestionEntry[] {
    if (
        new TextEncoder().encode(source).byteLength > MAX_SUGGESTION_FILE_BYTES
    ) {
        throw new Error(
            'CSV must be 2 MB or smaller. Choose a smaller UTF-8 text file.',
        );
    }
    const text = source.replace(/^\uFEFF/, '');
    if (/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/.test(text)) {
        throw new Error(
            'This file contains binary or unsupported control characters. Choose a UTF-8 CSV file.',
        );
    }
    const records = parseCsvRecords(text);
    if (!records.length) return [];

    const header = records[0].map(normalizeHeader);
    const nameHeaders = [
        'name',
        'school',
        'school name',
        'strand',
        'track',
        'program',
        'course',
        'previous course',
    ];
    const aliasHeaders = [
        'alias',
        'aliases',
        'alternate name',
        'alternate names',
    ];
    const nameColumn = header.findIndex((cell) => nameHeaders.includes(cell));
    const aliasesColumn = header.findIndex((cell) =>
        aliasHeaders.includes(cell),
    );
    const hasHeader = nameColumn >= 0;
    const start = hasHeader ? 1 : 0;
    const entries: SuggestionEntry[] = [];
    for (const record of records.slice(start)) {
        const label = (record[hasHeader ? nameColumn : 0] ?? '').trim();
        if (!label) continue;
        const aliasField = record[hasHeader ? aliasesColumn : 1] ?? '';
        const aliases = aliasField
            .split(/[|;]/)
            .flatMap((value) => value.split(','))
            .map((value) => value.replace(/[\r\n]+/g, ' ').trim())
            .filter(Boolean);
        entries.push({
            label: label.replace(/[\r\n]+/g, ' ').trim(),
            aliases: [...new Set(aliases)],
        });
        if (entries.length > MAX_SUGGESTION_ENTRIES)
            throw new Error(
                'CSV exceeds 5,000 entries. Split it into smaller files.',
            );
    }
    return entries;
}

export function appendSuggestionEntries(
    currentText: string,
    incoming: SuggestionEntry[],
): string {
    const addition = serializeSuggestionEntries(incoming);
    return [currentText.trimEnd(), addition].filter(Boolean).join('\n');
}
