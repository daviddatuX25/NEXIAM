import { describe, expect, it } from 'vitest';
import {
    appendSuggestionEntries,
    createSuggestionDrafts,
    parseSuggestionCsv,
    parseSuggestionDraft,
    serializeSuggestionEntries,
} from '../../resources/js/lib/application-suggestion-editor';

describe('application suggestion CSV staging', () => {
    it('reads BOM and CRLF CSV with quoted commas and ignores extra named columns', () => {
        expect(
            parseSuggestionCsv(
                '\uFEFFSCHOOL NAME,SCHOOL ID,ALIASES\r\n"North, East School",123,"NE, NES"\r\nPlain School,,PS\r\n',
            ),
        ).toEqual([
            { label: 'North, East School', aliases: ['NE', 'NES'] },
            { label: 'Plain School', aliases: ['PS'] },
        ]);
    });
    it('ignores SCHOOL ID when no aliases column is present', () => {
        expect(
            parseSuggestionCsv('SCHOOL NAME,SCHOOL ID\nNorth School,901'),
        ).toEqual([{ label: 'North School', aliases: [] }]);
    });

    it('keeps embedded quoted line breaks within a single name', () => {
        expect(
            parseSuggestionCsv('SCHOOL NAME\n"North\nCampus School"'),
        ).toEqual([{ label: 'North Campus School', aliases: [] }]);
    });

    it('reads headerless name-first rows and preserves a quoted comma in the name', () => {
        expect(
            parseSuggestionCsv(
                '"Science, Technology School",STS\nAnother School\n',
            ),
        ).toEqual([
            { label: 'Science, Technology School', aliases: ['STS'] },
            { label: 'Another School', aliases: [] },
        ]);
    });

    it('rejects malformed, binary, and oversized content', () => {
        expect(() => parseSuggestionCsv('"unfinished name')).toThrow(
            /not closed/i,
        );
        expect(() => parseSuggestionCsv('Name\u0000School')).toThrow(/binary/i);
        expect(() =>
            parseSuggestionCsv('x'.repeat(2 * 1024 * 1024 + 1)),
        ).toThrow(/2 MB/i);
    });
});

it('keeps literal pipes, commas and backslashes when staged names are saved or reopened', () => {
    const entries = [
        {
            label: 'North | Campus, Inc.',
            aliases: ['N|C', 'North, Campus', 'NC\\Main'],
        },
    ];
    expect(parseSuggestionDraft(serializeSuggestionEntries(entries))).toEqual([
        {
            label: 'North | Campus, Inc.',
            aliases: ['N|C', 'North, Campus', 'NC\\Main'],
        },
    ]);
    const csv = parseSuggestionCsv('"North | Campus, Inc.",NC');
    expect(parseSuggestionDraft(appendSuggestionEntries('', csv))).toEqual([
        { label: 'North | Campus, Inc.', aliases: ['NC'] },
    ]);
});

describe('application suggestion category drafts', () => {
    it('retains independent text and expected revisions when editing another category', () => {
        const drafts = createSuggestionDrafts({
            schools: {
                entries: [{ label: 'North School', aliases: ['NS'] }],
                revision: 3,
            },
            shs: { entries: [], revision: 0 },
            programs: {
                entries: [{ label: 'History', aliases: [] }],
                revision: 8,
            },
        });
        drafts.schools.text = 'North School | NS\nA name-only school';
        drafts.shs.text = 'STEM';
        expect(drafts.schools.revision).toBe(3);
        expect(drafts.programs.revision).toBe(8);
        expect(parseSuggestionDraft(drafts.schools.text)).toEqual([
            { label: 'North School', aliases: ['NS'] },
            { label: 'A name-only school', aliases: [] },
        ]);
        expect(
            appendSuggestionEntries(drafts.shs.text, [
                { label: 'HUMSS', aliases: [] },
            ]),
        ).toBe('STEM\nHUMSS');
        expect(drafts.schools.text).toBe(
            'North School | NS\nA name-only school',
        );
    });
});
